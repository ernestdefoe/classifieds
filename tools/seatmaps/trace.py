"""
Read a stadium seating chart and work out where each section sits on it.

🚨 Measured off the image with OCR word boxes, never estimated.

These coordinates decide where a star lands on a buyer's screen, and a star in
the wrong part of a ground is worse than none: people believe a picture over a
sentence. So a section's position comes from the pixel box Tesseract reports,
and anything several passes do not agree on is dropped rather than approximated.

🚨 Several passes, and agreement IS the confidence.
No single setting wins — measured on Bryant-Denny, 3x+autocontrast found 59
labels and 2x+threshold found 56, each catching some the other missed, and
`tessedit_char_whitelist` HURT every combination (tesseract 5's LSTM dislikes
it). So the chart is read a dozen ways and a label is kept when independent
passes put it in the same place.
"""
import json, os, re, subprocess, sys, tempfile
from collections import defaultdict
from PIL import Image, ImageOps

SECTION = re.compile(r'^[A-Z]{0,4}\d{0,4}[A-Z]{0,3}$')

STOP = {
    'PRESS', 'BOX', 'BOXES', 'SUITES', 'TERRACE', 'LODGE', 'CLUB', 'IVORY',
    'PRESIDENTS', 'PRESIDENT', 'SKYBOX', 'SKY', 'NORTH', 'SOUTH', 'EAST', 'WEST',
    'GATE', 'VIDEOBOARD', 'TUNNEL', 'FIELD', 'SUITE', 'STADIUM', 'VISITORS',
    'HOME', 'ENTRANCE', 'ELEVATOR', 'RAMP', 'CONCOURSE', 'PLAZA', 'DECK',
    'UPPER', 'LOWER', 'LOGE', 'STUDENT', 'BAND', 'SECTION', 'ROW', 'LEVEL',
    'TIER', 'PORTAL', 'AISLE', 'MEZZANINE', 'TERR', 'PREMIUM', 'CLUBHOUSE',
    'VISITOR', 'STANDING', 'ACCESSIBLE', 'FAMILY', 'GENERAL', 'ADMISSION',
}

# (preparation, page-segmentation mode) — the upscale is chosen per image.
PASSES = [('autocontrast', 11), ('threshold', 11), ('threshold', 12), ('autocontrast', 12)]

# 🚨 Scale to a WORKING WIDTH, not by a fixed multiplier.
#
# Tesseract wants glyphs of a certain height, which is a property of the
# rendered image rather than of the original. A fixed 2-3x suits a 1000px chart
# and starves a 500px one: on Notre Dame's 500x500 chart every pass read
# something and no two passes read the SAME thing, so 50 of 73 labels were
# dropped as uncorroborated and the chart traced zero. The same chart at a
# sensible working width reads cleanly.
WORKING_WIDTH = 2400
MAX_SCALE = 6
ROTATIONS = (0, 90, 180, 270)

# Tesseract's own confidence, below which a read is not considered at all.
MIN_CONF = 45


def prepare(img, mode):
    g = ImageOps.grayscale(img)
    g = ImageOps.autocontrast(g, cutoff=1)
    return g.point(lambda v: 0 if v < 190 else 255) if mode == 'threshold' else g


def ocr(img, psm):
    with tempfile.TemporaryDirectory() as d:
        p = os.path.join(d, 'i.png')
        img.save(p)
        r = subprocess.run(['tesseract', p, 'stdout', '--psm', str(psm), 'tsv'],
                           capture_output=True, text=True, timeout=180)
    out = []
    for line in r.stdout.splitlines()[1:]:
        f = line.split('\t')
        if len(f) < 12:
            continue
        try:
            conf = float(f[10])
            left, top, w, h = int(f[6]), int(f[7]), int(f[8]), int(f[9])
        except ValueError:
            continue
        t = f[11].strip().upper()
        if t:
            out.append((t, conf, left + w / 2, top + h / 2, h))
    return out


def unrotate(kind, x, y, W, H):
    """A point in a rotated image, back in the original's pixels."""
    if kind == 0:
        return x, y
    if kind == 90:      # PIL ROTATE_90 is counter-clockwise
        return W - 1 - y, x
    if kind == 180:
        return W - 1 - x, H - 1 - y
    return y, H - 1 - x  # 270


def on_the_turf(img, x, y):
    """
    🚨 Is this label printed on the playing surface?

    The yard numbers (10, 20, 30…) sit on the pitch and look exactly like
    section labels; a ground whose sections really are numbered 1-50 would
    otherwise collect phantom sections in the middle of the field.

    🚨 GREEN specifically, not "strongly coloured". The first version rejected
    any saturated background and so threw away every section on the modern
    charts, where sections are solid blue or red blocks with white numbers —
    Ohio State, Tennessee, Florida and Notre Dame all returned nothing at all,
    and the charts turned out to be the most legible of the lot.
    """
    w, h = img.size
    x, y = int(max(0, min(w - 1, x))), int(max(0, min(h - 1, y)))
    px = list(img.crop((max(0, x - 6), max(0, y - 6), min(w, x + 7), min(h, y + 7))).convert('RGB').getdata())
    if not px:
        return False

    green = sum(1 for r, g, b in px if g > r + 20 and g > b + 20)

    return green / len(px) > 0.5


def trace(path, min_agreement=2):
    base = Image.open(path).convert('RGB')
    W, H = base.size
    hits = defaultdict(list)

    # Two scales around the working width, so a chart is read at more than one
    # size and agreement still means something.
    base_scale = max(1, min(MAX_SCALE, round(WORKING_WIDTH / max(W, 1))))
    scales = sorted({base_scale, max(1, base_scale - 1)})

    for scale in scales:
      for mode, psm in PASSES:
        img = prepare(base.resize((W * scale, H * scale), Image.LANCZOS), mode)
        BW, BH = img.size

        for kind in ROTATIONS:
            rot = img if kind == 0 else img.transpose(
                {90: Image.ROTATE_90, 180: Image.ROTATE_180, 270: Image.ROTATE_270}[kind])
            for t, conf, cx, cy, gh in ocr(rot, psm):
                if conf < MIN_CONF or t in STOP or not SECTION.match(t) or not (1 <= len(t) <= 6):
                    continue
                if not any(c.isalnum() for c in t):
                    continue
                ox, oy = unrotate(kind, cx, cy, BW, BH)
                x, y = ox / scale, oy / scale
                if on_the_turf(base, x, y):
                    continue
                hits[t].append((x, y, conf, f'{scale}{mode[0]}{psm}r{kind}', gh / scale))

    sections, dropped = {}, {}
    tol = max(W, H) * 0.025

    # 🚨 A ground's name printed across the turf, or a title, is set far larger
    # than a section label. Measuring the typical glyph height separates them
    # without needing to know any particular stadium's wording.
    heights = sorted(h[4] for hs in hits.values() for h in hs)
    typical = heights[len(heights) // 2] if heights else 0

    for t, hs in hits.items():
        # Cluster the reads of one label; the same text in two parts of the
        # ground is a misread, not two sections, and averaging would put the
        # star between them — in neither.
        clusters = []
        for h in sorted(hs, key=lambda h: -h[2]):
            for c in clusters:
                if abs(h[0] - c[0][0]) <= tol and abs(h[1] - c[0][1]) <= tol:
                    c.append(h)
                    break
            else:
                clusters.append([h])

        clusters.sort(key=len, reverse=True)
        best = clusters[0]

        if typical and sum(h[4] for h in best) / len(best) > typical * 2.2:
            dropped[t] = 'set too large to be a section'
            continue

        # Ambiguous: two places read it about equally often.
        if len(clusters) > 1 and len(clusters[1]) >= len(best):
            dropped[t] = f'read in {len(clusters)} places'
            continue

        passes = {h[3] for h in best}
        if len(passes) < min_agreement:
            dropped[t] = f'only {len(passes)} pass agreed'
            continue

        sections[t] = {
            'x': round(sum(h[0] for h in best) / len(best) / W, 4),
            'y': round(sum(h[1] for h in best) / len(best) / H, 4),
            'agree': len(passes),
            'conf': round(max(h[2] for h in best)),
        }

    sections, collisions = one_label_per_place(sections, W, H)
    dropped.update(collisions)

    sections, weak = only_the_confident(sections)
    dropped.update(weak)

    return {'width': W, 'height': H, 'sections': sections, 'dropped': dropped}


def only_the_confident(sections):
    """
    🚨 Keep precision, give up recall.

    The positions OCR reports are good; the TEXT is where it breaks down. On a
    1044px chart the left-hand column read S4 as "34" and S8 as "8S" — right
    place, wrong name. A section traced under the wrong name is the one thing
    this must never produce: a buyer's "Section S4" would then match a marker
    sitting at S7.

    An untraced section is harmless — the listing falls back to the seller's own
    words — so anything not clearly corroborated is dropped. A label is kept
    when the chart contains its siblings (N5 beside N4 and N6), or when several
    passes read it confidently and it is long enough not to be a stray glyph.
    """
    kept, dropped = {}, {}

    for t, v in sections.items():
        if v.get('family', 0) >= 1:
            kept[t] = v
        elif len(t) >= 3 and v['agree'] >= 4 and v['conf'] >= 85:
            kept[t] = v
        else:
            dropped[t] = 'not corroborated'

    return kept, dropped


def one_label_per_place(sections, W, H):
    """
    🚨 One position, one section.

    Reading the chart at 180 degrees is necessary — the labels round the bottom
    of a bowl are printed upside down — but it also re-reads the top ones
    upside down, and Tesseract turns those into confident nonsense: "SNN" and
    "ONN" landed exactly on NN5 and NN6. Several passes agreeing does not help,
    because they agree on the same misreading.

    Two labels cannot occupy the same spot on a chart, so when they do, the
    better-supported one wins and the rest go.
    """
    tol = max(W, H) * 0.02
    order = sorted(sections, key=lambda t: -score(t, sections))

    kept, dropped = {}, {}
    for t in order:
        v = sections[t]
        v['family'] = family_size(t, sections)
        clash = next((k for k in kept
                      if abs(kept[k]['x'] - v['x']) * W <= tol and abs(kept[k]['y'] - v['y']) * H <= tol), None)
        if clash:
            dropped[t] = f'same place as {clash}'
        else:
            kept[t] = v

    return kept, dropped


def shape(t):
    """
    A label's shape: runs of letters and digits reduced to class plus length.
    N3 -> A1D1, 29D -> D2A1, 101 -> D3, EJU -> A3, W217 -> A1D3.

    🚨 General on purpose, twice over. The first version only understood
    PREFIX+digits and saw no family at all in Ohio State's 29D, 27D, 25D.
    Collapsing digit runs fixed that and still read Penn State's EJU, EHU, NLU
    as three unrelated names -- every all-letter label was its own family,
    which is no support at all. Shape has to describe the PATTERN, not the
    characters that happen to be left over.

    Measured head to head on six charts that had traced badly: 53 sections
    with the digit-collapse rule, 120 with this one.
    """
    return re.sub(r'(\d+|[^\d]+)',
                  lambda m: ('D' if m.group()[0].isdigit() else 'A') + str(len(m.group())),
                  t)


def family_size(t, sections):
    """How many labels on this chart share its shape: 29D's are 27D, 25D, 23D…"""
    mine = shape(t)

    return sum(1 for other in sections if other != t and shape(other) == mine)


def score(t, sections):
    """
    How much to trust one label: how many passes read it, how sure they were,
    and — the useful one — whether the chart contains its siblings.

    🚨 A section almost never stands alone. If N5 is real, N4 and N6 are on the
    same chart; a misreading like "SNN" has no family. That structure separates
    a real label from a confident misreading better than confidence does.
    """
    v = sections[t]

    return (min(family_size(t, sections), 6) * 100) + (v['agree'] * 10) + v['conf'] / 100


if __name__ == '__main__':
    res = trace(sys.argv[1])
    print(f"{len(res['sections'])} kept, {len(res['dropped'])} dropped")
    for k in sorted(res['sections'], key=lambda s: (len(s), s)):
        v = res['sections'][k]
        print(f"  {k:<6} {v['x']:.3f},{v['y']:.3f}  agree {v['agree']:>2}  conf {v['conf']}")
    if len(sys.argv) > 2:
        json.dump(res, open(sys.argv[2], 'w'), indent=1)
