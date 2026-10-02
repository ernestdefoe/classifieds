#!/usr/bin/env python3
"""Harvest Paciolan/eVenue seating charts AND their authored <area> geometry.

The pictures are useful; the image maps are the prize. Every tenant that draws a
chart with an HTML image map has already traced it -- section id plus polygon --
so the geometry comes out exact instead of OCR'd.

Rules that matter:
  * sequential, 8-14s apart, canary first (PerimeterX blocks by IP)
  * a 403 is UNKNOWN, never "this school has no chart" -- stop instead
  * pick the image by its alt text against the known venue name, never by
    filename and never "the biggest one"
  * save the image that carries the usemap, so the coordinates still line up
"""
import json, os, random, re, subprocess, sys, time, html as htmlmod

UA = ('Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 '
      '(KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36')
OUT = os.environ.get('CHART_OUT', '/root/charts2')
RESULT = os.environ.get('CHART_RESULT', '/root/evenue-result2.json')
BRANDS = os.environ.get('CHART_BRANDS', '/root/brands.txt')
LOG = sys.stdout

# 'memorial' is NOT a stopword: half a dozen grounds are called plainly
# "Memorial Stadium", and dropping it leaves those schools with nothing to
# match on at all.
STOP = {'stadium', 'field', 'arena', 'center', 'centre', 'at', 'the', 'of',
        'and', 'park', 'complex', 'university'}
# venues that are definitely not a football ground
OTHER = re.compile(r'arena|coliseum|basketball|baseball|softball|soccer|'
                   r'volleyball|hockey|natator|tennis|track|pavilion', re.I)
# 🚨 Another sport named in the PATH is a hard no, not a penalty. Illinois
# offered mbb-seatingchart-sfc.gif and Michigan michigan_soccer_stadium.gif,
# and both scored well enough on proximity alone to be filed as the football
# ground. The keyword is often in the directory, never just the filename.
NOT_FOOTBALL = re.compile(r'(^|[^a-z])(mbb|wbb|mbk|wbk|wvb|mvb|wsoc|msoc)([^a-z]|$)'
                          r'|basketball|baseball|softball|soccer|volleyball|'
                          r'hockey|tennis|golf|track|lacrosse|wrestling|'
                          r'gymnastics|swim', re.I)
FOOTBALL = re.compile(r'football|gridiron|fb(?![a-z])', re.I)


def curl(url, binary=False, timeout=45):
    cmd = ['curl', '-sS', '-L', '--max-time', str(timeout), '-A', UA,
           '-H', 'Accept-Language: en-US,en;q=0.9',
           '-w', '\n__CODE__%{http_code}', url]
    p = subprocess.run(cmd, capture_output=True, timeout=timeout + 15)
    raw = p.stdout
    i = raw.rfind(b'\n__CODE__')
    if i < 0:
        return 0, b'' if binary else ''
    code = int(raw[i + 9:].strip() or 0)
    body = raw[:i]
    return code, body if binary else body.decode('utf-8', 'replace')


def tokens(name):
    return {t for t in re.findall(r'[a-z0-9]+', (name or '').lower())
            if t not in STOP and len(t) > 2}


def centroid(shape, coords):
    """Centre of an <area> plus its size, so the biggest piece of a split
    section wins instead of whichever polygon happened to be written first."""
    try:
        n = [float(x) for x in coords.split(',') if x.strip() != '']
    except ValueError:
        return None
    shape = (shape or 'rect').lower()
    if shape.startswith('circ') and len(n) >= 3:
        return n[0], n[1], 3.14159 * n[2] * n[2]
    if shape.startswith('rect') and len(n) >= 4:
        return ((n[0] + n[2]) / 2, (n[1] + n[3]) / 2,
                abs(n[2] - n[0]) * abs(n[3] - n[1]))
    pts = list(zip(n[0::2], n[1::2]))
    if len(pts) < 3:
        return None
    if pts[0] == pts[-1]:
        pts = pts[:-1]
    a = cx = cy = 0.0
    for i in range(len(pts)):
        x0, y0 = pts[i]
        x1, y1 = pts[(i + 1) % len(pts)]
        cr = x0 * y1 - x1 * y0
        a += cr
        cx += (x0 + x1) * cr
        cy += (y0 + y1) * cr
    if abs(a) < 1e-9:
        return (sum(p[0] for p in pts) / len(pts),
                sum(p[1] for p in pts) / len(pts), 0.0)
    a *= 0.5
    return cx / (6 * a), cy / (6 * a), abs(a)


def section_of(area_tag):
    """The section label, from whichever attribute this tenant used."""
    for pat in (r'on[Mm]ouse[Oo]ver\s*=\s*"[^"(]*\(\s*[\'"]([^\'"]+)',
                r'alt\s*=\s*"([^"]+)"',
                r'title\s*=\s*"([^"]+)"',
                r'href\s*=\s*"[^"]*?(?:section|sect|zone)[=/]([^"&]+)'):
        m = re.search(pat, area_tag)
        if m:
            v = htmlmod.unescape(m.group(1)).strip()
            if v and v.lower() not in ('none', 'null', '', 'default'):
                v = re.sub(r'^(section|sec|sect)\s*[:#]?\s*', '', v, flags=re.I)
                v = v.strip().strip('.')
                if v and v.lower() != 'none' and len(v) <= 12:
                    return v
    return None


def maps_in(page):
    out = {}
    # name= is the usual spelling, but id= is legal and some tenants use only
    # that -- California's chart declares a usemap whose <map> we found no
    # other way.
    for m in re.finditer(r'<map\b([^>]*)>(.*?)</map>', page, re.I | re.S):
        attrs, body = m.group(1), m.group(2)
        nm = (re.search(r'\bname\s*=\s*"([^"]+)"', attrs, re.I)
              or re.search(r'\bid\s*=\s*"([^"]+)"', attrs, re.I))
        if not nm:
            continue
        name = nm.group(1)
        secs = {}
        for a in re.finditer(r'<area\b[^>]*>', body, re.I):
            tag = a.group(0)
            sec = section_of(tag)
            c = re.search(r'coords\s*=\s*"([^"]+)"', tag, re.I)
            s = re.search(r'shape\s*=\s*"([^"]+)"', tag, re.I)
            if not sec or not c:
                continue
            pt = centroid(s.group(1) if s else 'rect', c.group(1))
            # 🚨 FIRST in document order wins, not the biggest.
            # "Biggest piece of a split section" sounds better and is wrong:
            # Arkansas draws each 500-level strip early and then a broad deck
            # hotspot carrying the same label, so taking the larger one put
            # 507 on the Broyles Athletic Center and scattered 500-506 across
            # the lower bowl. The tenant lists the real seating block first.
            # This ordering is the one that was rendered and checked.
            if pt and sec not in secs:
                secs[sec] = pt
        if secs:
            out[name.lower().lstrip('#')] = {k: (v[0], v[1])
                                             for k, v in secs.items()}
    return out


def images_in(page):
    out = []
    for m in re.finditer(r'<img\b[^>]*>', page, re.I):
        tag = m.group(0)
        src = re.search(r'src\s*=\s*"([^"]+)"', tag, re.I)
        if not src:
            continue
        g = lambda p: (re.search(p, tag, re.I).group(1)
                       if re.search(p, tag, re.I) else '')
        out.append({
            'src': htmlmod.unescape(src.group(1)),
            'alt': htmlmod.unescape(g(r'alt\s*=\s*"([^"]*)"')),
            'id': g(r'\bid\s*=\s*"([^"]*)"'),
            'usemap': g(r'usemap\s*=\s*"([^"]*)"').lower().lstrip('#'),
            'w': int(g(r'\bwidth\s*=\s*"?(\d+)') or 0),
            'h': int(g(r'\bheight\s*=\s*"?(\d+)') or 0),
            'at': m.start(),
        })
    return out


def heading_near(page, pos):
    """Tenants without alt text print the venue name just above the image."""
    chunk = re.sub(r'<[^>]+>', ' ', page[max(0, pos - 500):pos])
    chunk = re.sub(r'&nbsp;?', ' ', chunk)
    return re.sub(r'\s+', ' ', htmlmod.unescape(chunk))


def pick(page, imgs, venue, amaps):
    """Score each image for 'this is the football ground we want'.

    🚨 Weak evidence is no evidence. A chart is only accepted when the venue's
    own words appear on it, or the page calls it football outright -- being
    merely near a heading is how a basketball arena gets filed as a stadium.
    """
    want = tokens(venue)
    best = None
    for im in imgs:
        path = im['src']
        label = ' '.join([im['alt'], im['id'], path])
        hit = tokens(label) & want

        # 🚨 Another sport named anywhere in the path or alt is a hard no, and
        # a venue-token hit does NOT excuse it: the token is nearly always the
        # school's own name, which appears in every file that tenant serves.
        # "michigan_soccer_stadium.gif" matched "Michigan Stadium" on exactly
        # that and was filed as the football ground twice. Only the page
        # calling it football outright can override.
        if ((NOT_FOOTBALL.search(path) or NOT_FOOTBALL.search(im['alt']))
                and not FOOTBALL.search(label)):
            continue

        strong = bool(hit) or bool(FOOTBALL.search(label))
        if not strong:
            continue                        # proximity alone decides nothing

        score = 6 * len(hit)
        if FOOTBALL.search(label):
            score += 5
        near = heading_near(page, im['at'])
        score += 2 * len(tokens(near[-160:]) & want)
        if OTHER.search(im['alt']) and not FOOTBALL.search(label):
            score -= 6
        if im['usemap'] and im['usemap'] in amaps:
            # The geometry IS the prize. A prettier unmapped picture of the same
            # ground is worth much less than a plain one we can put a star on.
            score += 12
        px = (im['w'] * im['h']) or 0
        if px and px < 40000:
            score -= 5                      # 200x150 'view from seat' thumbs

        if score > 0 and (best is None or score > best[0]
                          or (score == best[0] and px > best[1]['w'] * best[1]['h'])):
            best = (score, im, sorted(hit))
    return best


def absolute(base, src):
    if src.startswith('http'):
        return src
    if src.startswith('//'):
        return 'https:' + src
    return base.rstrip('/') + '/' + src.lstrip('/')


def real_size(path):
    try:
        p = subprocess.run(['identify', '-format', '%w %h', path + '[0]'],
                           capture_output=True, timeout=30)
        w, h = p.stdout.decode().split()[:2]
        return int(w), int(h)
    except Exception:
        return 0, 0


CANARY = ('https://arkansasrazorbacks.evenue.net/evenue/linkID=arkansas'
          '/core/seating_charts.html')


def blocked():
    """Is this a throttle, or just an unknown tenant?

    🚨 eVenue answers 403 for BOTH: PerimeterX shutting us out, and a brand
    subdomain nobody has configured -- *.evenue.net is a wildcard, so every
    guess resolves and most of them are nothing. Treating the two alike stopped
    a whole run on its second school. So on any 403, ask a host we know works:
    if the canary still answers, the 403 belonged to that tenant alone.
    """
    time.sleep(random.uniform(10, 16))
    code, _ = curl(CANARY)
    print(f'    403 seen -- canary says {code}', flush=True)
    return code != 200


def main():
    os.makedirs(OUT, exist_ok=True)
    schools = []
    for line in open(BRANDS):
        parts = line.strip().split('|')
        if len(parts) >= 2 and parts[1]:
            schools.append((parts[0], parts[1], parts[2] if len(parts) > 2 else ''))

    done = {}
    if os.path.exists(RESULT):
        for r in json.load(open(RESULT)):
            if r.get('status') in ('ok', 'no-charts', 'no-linkID', 'no-match',
                                   'no-tenant', 'chart page 403'):
                done[r['team']] = r

    code, _ = curl(CANARY)
    print(f'canary: {code}', flush=True)
    if code != 200:
        print('canary failed -- not harvesting, a 403 would record false absences')
        return

    results = list(done.values())
    print(f'{len(schools)} schools, {len(done)} already done', flush=True)

    for n, (team, brand, venue) in enumerate(schools, 1):
        if team in done:
            continue
        time.sleep(random.uniform(15, 25))
        base = f'https://{brand}.evenue.net'
        rec = {'team': team, 'brand': brand, 'venue': venue}

        code, home = curl(base + '/')
        if code == 403:
            if blocked():
                print(f'403 on {team} and the canary agrees -- stopping', flush=True)
                break
            rec['status'] = 'no-tenant'
            results.append(rec); json.dump(results, open(RESULT, 'w'), indent=1)
            print(f'{n:3} -- {team:18} no tenant at that brand (403)', flush=True)
            continue
        link = re.search(r'linkID=([A-Za-z0-9_\-]+)', home or '')
        if not link:
            rec['status'] = 'no-linkID'
            results.append(rec); json.dump(results, open(RESULT, 'w'), indent=1)
            print(f'{n:3} -- {team:18} no linkID', flush=True)
            continue
        lid = link.group(1)
        rec['linkID'] = lid
        root = f'{base}/evenue/linkID={lid}'

        time.sleep(random.uniform(15, 25))
        code, page = curl(f'{root}/core/seating_charts.html')
        if code == 403:
            if blocked():
                print(f'403 on {team} chart page and the canary agrees -- stopping', flush=True)
                break
            rec['status'] = 'chart page 403'
            results.append(rec); json.dump(results, open(RESULT, 'w'), indent=1)
            print(f'{n:3} -- {team:18} chart page 403 (tenant, not a block)', flush=True)
            continue
        if code != 200 or not page:
            rec['status'] = f'page {code}'
            results.append(rec); json.dump(results, open(RESULT, 'w'), indent=1)
            print(f'{n:3} -- {team:18} page {code}', flush=True)
            continue

        imgs = images_in(page)
        amaps = maps_in(page)
        rec['images'] = len(imgs)
        rec['maps'] = {k: len(v) for k, v in amaps.items()}
        if not imgs:
            rec['status'] = 'no-charts'
            results.append(rec); json.dump(results, open(RESULT, 'w'), indent=1)
            print(f'{n:3} -- {team:18} tenant publishes no charts', flush=True)
            continue

        chosen = pick(page, imgs, venue, amaps)
        if not chosen:
            rec['status'] = 'no-match'
            rec['alts'] = [i['alt'] or i['src'] for i in imgs][:12]
            results.append(rec); json.dump(results, open(RESULT, 'w'), indent=1)
            print(f'{n:3} -- {team:18} {len(imgs)} imgs, none matched venue', flush=True)
            continue

        score, im, hit = chosen
        url = absolute(root + '/core/', im['src'])
        ext = os.path.splitext(url.split('?')[0])[1].lower() or '.jpg'
        if ext not in ('.jpg', '.jpeg', '.png', '.gif'):
            ext = '.jpg'
        safe = re.sub(r'[^A-Za-z0-9]+', '_', team).strip('_')
        path = f'{OUT}/{safe}{ext}'

        time.sleep(random.uniform(15, 25))
        code, blob = curl(url, binary=True)
        if code == 403 and blocked():
            print(f'403 fetching {team} image and the canary agrees -- stopping', flush=True)
            break
        if code != 200 or len(blob) < 3000:
            rec['status'] = f'image {code} {len(blob)}b'
            results.append(rec); json.dump(results, open(RESULT, 'w'), indent=1)
            print(f'{n:3} -- {team:18} image {code}', flush=True)
            continue
        open(path, 'wb').write(blob)

        rw, rh = real_size(path)
        # image-map coords live in the RENDERED box, so scale by the declared
        # attributes when they disagree with the file's own pixels
        dw = im['w'] or rw
        dh = im['h'] or rh
        secs = amaps.get(im['usemap'], {}) if im['usemap'] else {}
        frac, outside = {}, 0
        if secs and dw and dh:
            for sec, (x, y) in secs.items():
                fx, fy = x / dw, y / dh
                if 0 <= fx <= 1 and 0 <= fy <= 1:
                    frac[sec] = [round(fx, 5), round(fy, 5)]
                else:
                    outside += 1
        # 🚨 Areas landing off the picture mean the map was authored for a
        # DIFFERENT image -- a chart the tenant has since redrawn. Fresno's
        # fitted inside the box but sat about five sections out, and 8 of its
        # 37 shapes fell outside: that overhang was the only warning. Keep the
        # picture, throw the geometry away.
        if secs and outside > max(1, 0.05 * len(secs)):
            rec['geometry_rejected'] = f'{outside} of {len(secs)} areas off-image'
            frac = {}

        rec.update({'status': 'ok', 'score': score, 'matched': hit,
                    'alt': im['alt'], 'url': url, 'saved': path,
                    'declared': [dw, dh], 'real': [rw, rh],
                    'usemap': im['usemap'], 'sections': frac})
        results.append(rec); json.dump(results, open(RESULT, 'w'), indent=1)
        print(f'{n:3} OK {team:18} {os.path.basename(path)} '
              f'{rw}x{rh} score={score} sections={len(frac)} '
              f'alt="{im["alt"][:40]}"', flush=True)

    json.dump(results, open(RESULT, 'w'), indent=1)
    ok = [r for r in results if r.get('status') == 'ok']
    print(f'\ndone: {len(ok)} charts, '
          f'{sum(len(r.get("sections", {})) for r in ok)} sections traced', flush=True)


if __name__ == '__main__':
    main()
