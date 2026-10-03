#!/usr/bin/env python3
"""Fetch each school's seating chart from the school's OWN athletics site.

Why this and not a ticketing provider: these are ~130 different hosts, each
asked for two or three pages, so there is no single service being leaned on and
nothing to work around. It is also where the big files are -- schools publish
PDFs and 1500px+ diagrams, where eVenue serves 619px GIFs that OCR cannot read
at all.

The hop that matters: a SidearmSports `/documents/<uuid>.pdf` answers 200 with
`text/html` and ~400KB of page. That is not a bot wall, it is how the platform
publishes documents, and the real file is named inside that HTML -- usually on
storage.googleapis.com. The scoring below is the same as the extension's own
SeatMapImporter::documentUrlWithin, deliberately, so the two agree.

🚨 Nothing it finds is loaded without being looked at. Search and link-following
return confidently wrong charts: Michigan's best match was once a real Michigan
Stadium diagram drawn for a Liverpool v Manchester United match, with soccer
pricing and both clubs printed on the field.
"""
import base64, json, os, re, random, subprocess, sys, time
from urllib.parse import urljoin, urlparse, unquote, parse_qs

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
import pdf_trace

UA = ('Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 '
      '(KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36')

WORK = os.environ.get('SEATMAP_WORK', os.path.abspath('.'))
OUT = f'{WORK}/school-charts'
RESULT = f'{WORK}/school-harvest.json'
TARGETS = os.environ.get('SEATMAP_TARGETS', f'{WORK}/targets.txt')

PAUSE = (4, 8)            # between requests, and these are all different hosts
MAX_PAGES = 5             # pages looked at per school, before the asset itself

FURNITURE = re.compile(r'logo|icon|favicon|sponsor|avatar|banner|header|footer'
                       r'|thumb|social|placeholder', re.I)
ASSET = re.compile(r'https?://[^\s"\'<>]+\.(?:pdf|png|jpe?g|gif|webp)', re.I)
OBJECT_STORE = re.compile(r'storage\.googleapis\.com|s3[.-][a-z0-9-]*amazonaws\.com'
                          r'|cloudfront\.net|blob\.core\.windows\.net', re.I)
RESIZER = re.compile(r'imgproxy|/rs:fit|/resize|[?&]w=\d+', re.I)
WRONG_SPORT = re.compile(r'basketball|baseball|softball|soccer|volleyball|hockey'
                         r'|lacrosse|tennis|\bmbb\b|\bwbb\b|commencement|graduation'
                         r'|concert', re.I)
# 🚨 A premium-seating page sells one corner of the ground, and its pictures are
# marketing shots, not charts. Iowa's best match was
# "premium_seats_kinnick_edge_400x280.jpg" -- it matched on "seat".
PARTIAL = re.compile(r'premium|club|suite|hospitality|vip|loge|box[-_]seat|rail',
                     re.I)
# A filename that states its own size is a derivative, not the document.
SIZED = re.compile(r'[-_](\d{2,4})x(\d{2,4})(?=[-_.]|$)')
MIN_WIDTH = 800        # below this the section numbers are unreadable anyway


def curl(url, binary=False, timeout=30):
    p = subprocess.run(
        # 🚨 --compressed, or every page arrives as gzip and scores as noise.
        # Both test schools reported "nothing that scored as a chart" with the
        # chart links right there in the markup.
        ['curl', '-sS', '-L', '--compressed', '--max-time', str(timeout), '-A', UA,
         '-H', 'Accept-Language: en-US,en;q=0.9',
         '-w', '\n__CODE__%{http_code}', url],
        capture_output=True, timeout=timeout + 15)
    raw = p.stdout
    i = raw.rfind(b'\n__CODE__')
    if i < 0:
        return 0, b'' if binary else ''
    code = int(raw[i + 9:].strip() or 0)
    body = raw[:i]
    return code, body if binary else body.decode('utf-8', 'replace')


def disallowed(host):
    """Paths robots.txt asks every agent to leave alone. Asked once per host."""
    code, txt = curl(f'https://{host}/robots.txt', timeout=15)
    if code != 200 or not txt:
        return []
    rules, applies = [], False
    for line in txt.splitlines():
        line = line.split('#')[0].strip()
        if not line:
            continue
        k, _, v = line.partition(':')
        k, v = k.strip().lower(), v.strip()
        if k == 'user-agent':
            applies = v == '*'
        elif k == 'disallow' and applies and v:
            rules.append(v)
    return rules


def allowed(path, rules):
    return not any(path.startswith(r) for r in rules)


def links_in(html, base):
    """Links worth following, best first: a seating chart page beats tickets."""
    out = []
    for m in re.finditer(r'<a\b[^>]*href\s*=\s*["\']([^"\']+)["\']([^>]*)>(.{0,120}?)</a>',
                         html, re.I | re.S):
        href, text = m.group(1), re.sub(r'<[^>]+>', ' ', m.group(3))
        blob = f'{href} {text}'.lower()
        if WRONG_SPORT.search(blob):
            continue
        score = 0
        if re.search(r'seat', blob):
            score += 8
        if re.search(r'chart|map|diagram', blob):
            score += 5
        if re.search(r'football', blob):
            score += 4
        if re.search(r'ticket', blob):
            score += 2
        if score >= 7:
            out.append((score, urljoin(base, href)))
    seen, ranked = set(), []
    for score, u in sorted(out, key=lambda x: -x[0]):
        if u not in seen:
            seen.add(u)
            ranked.append((score, u))
    return ranked


def proxied_assets(html):
    """The real files behind a CDN's resizing proxy.

    🚨 Sidearm sites serve content images only through images.sidearmdev.com
    (the source sits in a `url=` query parameter) or through an imgproxy path
    that base64-encodes it. Michigan State's seat map and Auburn's stripe-out
    chart are both reachable ONLY this way -- scanning for plain .jpg/.png
    links finds the sponsor logos and misses the chart entirely.
    """
    out = []
    for m in re.finditer(r'https?://images\.sidearmdev\.com/[a-z]+\?([^"\'<>\s]+)',
                         html):
        q = parse_qs(m.group(1))
        if 'url' in q:
            out.append(unquote(q['url'][0]))
    for m in re.finditer(r'/imgproxy/[^"\'<>\s]*/([A-Za-z0-9_\-]{24,})', html):
        for pad in ('', '=', '==', '==='):
            try:
                d = base64.urlsafe_b64decode(m.group(1) + pad).decode('utf-8')
                if d.startswith('http'):
                    out.append(d)
                break
            except Exception:
                pass
    return out


def assets_in(html, page_url):
    """Every plausible document on a page, best first -- scored, never 'the
    first' and never 'the biggest'. Mirrors the extension's own
    SeatMapImporter::documentUrlWithin, plus the rejections above."""
    html = html.replace('\\/', '/')
    out = []
    found = list(ASSET.findall(html)) + proxied_assets(html)
    for cand in dict.fromkeys(found):
        if cand.lower() == page_url.lower():
            continue
        path = (urlparse(cand).path or '').lower()
        if FURNITURE.search(path):
            continue
        score = 0
        # 🚨 A name that says what the file IS beats one that says only what
        # format it is. Iowa publishes "kinnick-seating-map.png" beside three
        # opaquely-named PDFs, and weighting .pdf above the keyword ranked the
        # unknowns first.
        if path.endswith('.pdf'):
            score += 6
        if re.search(r'seat|chart|diagram', path):
            score += 10
        elif re.search(r'map|stadium', path):
            score += 6
        if OBJECT_STORE.search(urlparse(cand).netloc or ''):
            score += 4
        if RESIZER.search(cand):
            score -= 6
        if WRONG_SPORT.search(path):
            score -= 12
        if PARTIAL.search(path):
            score -= 8
        m = SIZED.search(path)
        if m and max(int(m.group(1)), int(m.group(2))) < MIN_WIDTH:
            score -= 10
        if score >= 6:
            out.append((score, cand))
    out.sort(key=lambda x: -x[0])
    return out


def looks_like_html(b):
    head = b[:512].lstrip().lower()
    return head.startswith(b'<!doctype') or head.startswith(b'<html') or head.startswith(b'<?xml')


def to_png(path, target=1800):
    """Render a PDF's first page at a dpi chosen for THIS document.

    🚨 Never a fixed dpi. A vector chart's page size in points says nothing
    about its detail: California publishes Memorial Stadium on a 240x172pt
    page, which is 500px at 150dpi and was thrown away by a minimum-width
    check -- while the artwork scales perfectly to any size asked for.
    """
    if open(path, 'rb').read(4) != b'%PDF':
        return path
    pts = 0
    try:
        info = subprocess.run(['pdfinfo', path], capture_output=True, timeout=60)
        m = re.search(r'Page size:\s+([\d.]+) x ([\d.]+)',
                      info.stdout.decode('utf-8', 'replace'))
        if m:
            pts = max(float(m.group(1)), float(m.group(2)))
    except Exception:
        pass
    dpi = 150
    if pts:
        dpi = int(max(150, min(600, target * 72.0 / pts)))
    stem = path[:-4]
    subprocess.run(['pdftoppm', '-png', '-r', str(dpi), '-f', '1', '-l', '1',
                    path, stem], capture_output=True, timeout=300)
    for s in ('-1.png', '-01.png', '-001.png', '.png'):
        if os.path.exists(stem + s):
            return stem + s
    return None

def main():
    os.makedirs(OUT, exist_ok=True)
    targets = []
    for line in open(TARGETS):
        p = line.strip().split('|')
        if len(p) >= 3 and p[1]:
            targets.append((p[0], p[1], p[2]))

    done = {}
    if os.path.exists(RESULT):
        for r in json.load(open(RESULT)):
            done[r['team']] = r
    results = list(done.values())
    print(f'{len(targets)} schools, {len(done)} already done', flush=True)

    for n, (team, brand, venue) in enumerate(targets, 1):
        if team in done:
            continue
        host = brand if '.' in brand else f'{brand}.com'
        rec = {'team': team, 'brand': brand, 'venue': venue, 'host': host}
        time.sleep(random.uniform(*PAUSE))

        rules = disallowed(host)
        time.sleep(random.uniform(*PAUSE))

        code, home = curl(f'https://{host}/')
        if code != 200 or not home:
            rec['status'] = f'home {code}'
            results.append(rec); json.dump(results, open(RESULT, 'w'), indent=1)
            print(f'{n:3} -- {team:18} home {code}', flush=True)
            continue

        # Gather candidates across several pages FIRST, then try them in score
        # order. Stopping at the first page that yields anything took Iowa's
        # premium-seating advert and never looked at the chart.
        # Pages these sites reliably have. Search confirmed both on the schools
        # tested, and they carry the chart where the homepage does not.
        slug = re.sub(r'[^a-z0-9]+', '-', venue.lower()).strip('-')
        slug = re.sub(r'-?\(.*?\)-?', '', slug)
        guessed = [(90, f'https://{host}/tickets/football'),
                   (89, f'https://{host}/{slug}'),
                   (88, f'https://{host}/tickets')]
        pages = ([(99, f'https://{host}/')] + guessed
                 + links_in(home, f'https://{host}/'))
        cands, tried, seen_pages = [], 0, set()
        for _, url in pages:
            if tried >= MAX_PAGES:
                break
            if url in seen_pages or not allowed(urlparse(url).path or '/', rules):
                continue
            seen_pages.add(url)
            tried += 1
            if url == f'https://{host}/':
                page = home
            else:
                time.sleep(random.uniform(*PAUSE))
                code, page = curl(url)
                if code != 200 or not page:
                    continue
            for sc, a in assets_in(page, url):
                cands.append((sc, a, url))

        cands.sort(key=lambda x: -x[0])
        rec['candidates'] = len(cands)
        rec['pages_tried'] = tried

        chosen = None
        for score, asset, from_page in cands[:4]:
            time.sleep(random.uniform(*PAUSE))
            code, blob = curl(asset, binary=True)

            # the SidearmSports hop: a document URL that answers with a page
            if code == 200 and looks_like_html(blob):
                inner = assets_in(blob.decode('utf-8', 'replace'), asset)
                if not inner:
                    continue
                time.sleep(random.uniform(*PAUSE))
                code, blob = curl(inner[0][1], binary=True)
                asset = inner[0][1]

            if code != 200 or len(blob) < 5000 or looks_like_html(blob):
                continue

            safe = re.sub(r'[^A-Za-z0-9]+', '_', team).strip('_')
            ext = os.path.splitext(urlparse(asset).path)[1].lower() or '.bin'
            raw = f'{OUT}/{safe}{ext}'
            open(raw, 'wb').write(blob)
            png = to_png(raw)
            if not png:
                continue

            try:
                from PIL import Image
                w, h = Image.open(png).size
            except Exception:
                w = h = 0

            # 🚨 A small image is a thumbnail or an advert, never a chart whose
            # section numbers anyone could read.
            if w < MIN_WIDTH:
                os.remove(png)
                if os.path.exists(raw) and raw != png:
                    os.remove(raw)
                continue

            chosen = (asset, score, from_page, png, w, h, raw)
            break

        if not chosen:
            rec['status'] = 'no chart found'
            results.append(rec); json.dump(results, open(RESULT, 'w'), indent=1)
            print(f'{n:3} -- {team:18} nothing usable '
                  f'({tried} pages, {len(cands)} candidates)', flush=True)
            continue

        asset, score, from_page, png, w, h, raw = chosen

        # 🚨 The text layer first, always. Where a chart is a vector PDF its
        # section names are real text, and reading them beats OCR outright --
        # Penn State goes from 0 sections to 113, exactly placed.
        sections = {}
        if raw.lower().endswith('.pdf'):
            try:
                sections = pdf_trace.trace(raw)['sections']
            except Exception as e:
                rec['pdf_trace_error'] = str(e)
        rec.update({'status': 'ok', 'url': asset, 'from': from_page,
                    'score': score, 'saved': png, 'size': [w, h],
                    'pdf': raw if raw.lower().endswith('.pdf') else None,
                    'sections': sections})
        results.append(rec); json.dump(results, open(RESULT, 'w'), indent=1)
        print(f'{n:3} OK {team:18} {os.path.basename(png):26} {w}x{h} '
              f'text-layer={len(sections)}', flush=True)

    json.dump(results, open(RESULT, 'w'), indent=1)
    ok = [r for r in results if r.get('status') == 'ok']
    print(f'\n{len(ok)} charts downloaded, all UNVERIFIED -- look at them before '
          f'anything is loaded', flush=True)


if __name__ == '__main__':
    main()
