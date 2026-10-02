#!/usr/bin/env python3
"""Read section positions out of a PDF chart's TEXT LAYER, not its pixels.

🚨 Try this before OCR, always.

Schools publish their charts as vector PDFs drawn in Illustrator, and the
section names in them are usually real text. `pdftotext -bbox` then gives the
exact box of every label, which is the same quality of answer as a ticketing
provider's image map and for the same reason: nobody guessed it.

What that buys over OCR is not recall, it is PRECISION. OCR on the same Penn
State chart returns `HLYON` for NORTH, `JOWS` for ROWS and `ADOUES` for
nothing at all, and no stop list can anticipate a misreading. Here the words
are exact, so a short list of chart furniture -- GATE, PARK, SUITES -- removes
all of it.

A chart with no text layer (a scan, or type converted to outlines) yields
nothing here; fall back to trace.py for those.
"""
import json, os, re, subprocess, sys

# Chart furniture. Short and sufficient, because the text is exact.
STOP = {
    'GATE', 'GATES', 'PARK', 'PARKING', 'HOME', 'VISITORS', 'VISITOR', 'SUITES',
    'SUITE', 'PRESS', 'BOX', 'ROAD', 'DRIVE', 'AVENUE', 'STREET', 'NORTH',
    'SOUTH', 'EAST', 'WEST', 'ENTRANCE', 'ELEVATOR', 'ESCALATOR', 'RAMP',
    'CONCOURSE', 'PLAZA', 'DECK', 'UPPER', 'LOWER', 'CLUB', 'LOGE', 'STUDENT',
    'BAND', 'SECTION', 'SECTIONS', 'ROW', 'ROWS', 'SEAT', 'SEATS', 'SEATING',
    'LEVEL', 'TIER', 'PORTAL', 'AISLE', 'FIELD', 'STADIUM', 'CAPACITY', 'KEY',
    'GUIDE', 'ADA', 'EMS', 'FIRST', 'AID', 'POLICE', 'MUSEUM', 'STORE', 'SHOP',
    'TICKET', 'TICKETS', 'WILL', 'CALL', 'SALES', 'INFO', 'LOUNGE', 'TUNNEL',
    'VIDEOBOARD', 'SCOREBOARD', 'HILL', 'TERRACE', 'PAVILION', 'BENCH',
    'CHAIRBACK', 'BLEACHERS', 'GENERAL', 'ADMISSION', 'PREMIUM', 'RESERVED',
    'ENTRY', 'POINT', 'BUS', 'TEAM', 'ARRIVAL', 'SHUTTLE', 'DROP', 'CANOPY',
    'LOUNGE', 'LOT', 'NOT', 'SEE', 'THE', 'AND', 'FOR', 'ALL', 'OPEN',
}

SECTION = re.compile(r'^[A-Z]{0,4}\d{0,4}[A-Z]{0,3}$')
WORD = re.compile(r'<word xMin="([\d.]+)" yMin="([\d.]+)" '
                  r'xMax="([\d.]+)" yMax="([\d.]+)">([^<]*)</word>')
PAGE = re.compile(r'<page width="([\d.]+)" height="([\d.]+)"')


def words_in(pdf, page=1):
    """Every word on a page as (text, cx, cy, height) in page fractions."""
    out = subprocess.run(
        ['pdftotext', '-bbox', '-f', str(page), '-l', str(page), pdf, '-'],
        capture_output=True, timeout=120)
    html = out.stdout.decode('utf-8', 'replace')
    pg = PAGE.search(html)
    if not pg:
        return []
    W, H = float(pg.group(1)), float(pg.group(2))
    if not W or not H:
        return []
    found = []
    for m in WORD.finditer(html):
        x0, y0, x1, y1 = (float(m.group(i)) for i in range(1, 5))
        text = (m.group(5) or '').strip()
        if text:
            found.append((text, (x0 + x1) / 2 / W, (y0 + y1) / 2 / H,
                          (y1 - y0) / H))
    return found


def trace(pdf, page=1):
    words = words_in(pdf, page)
    if not words:
        return {'sections': {}, 'dropped': {'*': 'no text layer'}}

    # 🚨 "GATE F" leaves an F sitting on the chart exactly where a section
    # label would be, and F is a perfectly good section name elsewhere. The
    # word BEFORE it is what tells them apart, and a text layer preserves that
    # order -- OCR does not.
    PRECEDED_BY = {'GATE', 'GATES', 'ENTRANCE', 'ENTRY', 'LOT', 'DOOR',
                   'WINDOW', 'STAND', 'TOWER', 'RAMP', 'ELEVATOR'}

    cand = []
    for i, (text, cx, cy, h) in enumerate(words):
        # 🚨 ...and only when that word is actually NEXT TO it. Reading order in
        # a PDF is not spatial: USC's section 322 follows an unrelated "GATE"
        # from the other side of the page, and a bare preceding-word test threw
        # away real sections without a sound.
        if i:
            pt, px, py, _ = words[i - 1]
            if (pt.upper().strip('.,:;()[]') in PRECEDED_BY
                    and abs(py - cy) < 0.012 and 0 < cx - px < 0.06):
                continue
        t = text.upper().strip('.,:;()[]')
        if not t or t in STOP or not SECTION.match(t) or not (1 <= len(t) <= 6):
            continue
        if not any(c.isalnum() for c in t):
            continue
        cand.append((t, cx, cy, h))

    if not cand:
        return {'sections': {}, 'dropped': {'*': 'no section-shaped text'}}

    # 🚨 A title is set far larger than a section label. Measuring the typical
    # height separates them without knowing any stadium's wording.
    heights = sorted(c[3] for c in cand)
    typical = heights[len(heights) // 2]

    seen, dropped = {}, {}
    for t, cx, cy, h in cand:
        if h > typical * 2.0:
            dropped[t] = 'set too large to be a section'
            continue
        if h < typical * 0.45:
            dropped[t] = 'set too small to be a section'
            continue
        seen.setdefault(t, []).append((cx, cy))

    sections = {}
    for t, places in seen.items():
        if len(places) == 1:
            sections[t] = {'x': round(places[0][0], 5), 'y': round(places[0][1], 5)}
            continue
        # 🚨 The same name in two parts of a ground is a legend reference or a
        # repeated tier, not two sections. Averaging would put the star between
        # them, in neither; so keep it only when the places agree.
        xs = [p[0] for p in places]
        ys = [p[1] for p in places]
        if max(xs) - min(xs) < 0.05 and max(ys) - min(ys) < 0.05:
            sections[t] = {'x': round(sum(xs) / len(xs), 5),
                           'y': round(sum(ys) / len(ys), 5)}
        else:
            dropped[t] = f'printed in {len(places)} places'

    # 🚨 Do NOT try to drop the legend by how isolated it is. Measured on Penn
    # State, whose ticket-window numbers and "revised July 26, 2012" both read
    # as sections: the junk had 9-11 neighbours within 0.15 and the sparsest
    # REAL section had 7. There is no cut that removes one without the other.
    # The few strays per chart are removed by hand at review, in decisions.json.

    return {'sections': sections, 'dropped': dropped}


if __name__ == '__main__':
    res = trace(sys.argv[1], int(sys.argv[2]) if len(sys.argv) > 2 else 1)
    print(f"{len(res['sections'])} sections, {len(res['dropped'])} dropped")
    for k in sorted(res['sections'], key=lambda s: (len(s), s)):
        v = res['sections'][k]
        print(f"  {k:<6} {v['x']:.3f},{v['y']:.3f}")
    if res['dropped']:
        print('dropped:', res['dropped'])
