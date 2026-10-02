#!/usr/bin/env python3
"""Given candidate URLs per school, keep the one that traces best.

🚨 Do not try to guess which document is the chart. Judge by the answer.

Penn State publishes several PDFs. Picking by filename took one with no text
layer at all; the one search pointed at reads 113 sections exactly. The score
that matters is how many sections a candidate actually yields, so fetch a few
and let them compete -- a parking map scores 0 and loses on its own merits,
with no rule needed to describe it.

Input is `team|venue|url` lines, several per team, best guess first.
"""
import json, os, random, re, subprocess, sys, time
from urllib.parse import urlparse

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
import pdf_trace

UA = ('Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 '
      '(KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36')
WORK = os.environ.get('SEATMAP_WORK', os.path.abspath('.'))
OUT = f'{WORK}/charts'
RESULT = f'{WORK}/chart-candidates.json'
CANDS = os.environ.get('SEATMAP_CANDIDATES', f'{WORK}/candidates.txt')
PAUSE = (3, 6)
MIN_WIDTH = 800

ASSET = re.compile(r'https?://[^\s"\'<>]+\.(?:pdf|png|jpe?g|gif|webp)', re.I)
FURNITURE = re.compile(r'logo|icon|favicon|sponsor|avatar|banner|thumb|social', re.I)


def curl(url, timeout=45):
    p = subprocess.run(
        ['curl', '-sS', '-L', '--compressed', '--max-time', str(timeout), '-A', UA,
         '-w', '\n__CODE__%{http_code}', url],
        capture_output=True, timeout=timeout + 15)
    raw = p.stdout
    i = raw.rfind(b'\n__CODE__')
    if i < 0:
        return 0, b''
    return int(raw[i + 9:].strip() or 0), raw[:i]


def looks_like_html(b):
    head = b[:512].lstrip().lower()
    return (head.startswith(b'<!doctype') or head.startswith(b'<html')
            or head.startswith(b'<?xml'))


def document_in(html, page_url):
    """The file a SidearmSports viewer page is actually showing."""
    html = html.replace('\\/', '/')
    best, best_score = None, 0
    for cand in dict.fromkeys(ASSET.findall(html)):
        if cand.lower() == page_url.lower():
            continue
        path = (urlparse(cand).path or '').lower()
        if FURNITURE.search(path):
            continue
        score = 6 if path.endswith('.pdf') else 0
        if re.search(r'seat|chart|diagram', path):
            score += 10
        elif re.search(r'map|stadium', path):
            score += 6
        if re.search(r'storage\.googleapis\.com|amazonaws|cloudfront', cand, re.I):
            score += 4
        if score > best_score:
            best, best_score = cand, score
    return best


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

def try_url(team, url, idx):
    """Fetch one candidate, through the viewer hop if needed, and trace it."""
    code, blob = curl(url)
    hopped = None
    if code == 200 and looks_like_html(blob):
        inner = document_in(blob.decode('utf-8', 'replace'), url)
        if not inner:
            return None
        hopped = inner
        time.sleep(random.uniform(*PAUSE))
        code, blob = curl(inner)
    if code != 200 or len(blob) < 5000 or looks_like_html(blob):
        return None

    src = hopped or url
    safe = re.sub(r'[^A-Za-z0-9]+', '_', team).strip('_')
    ext = os.path.splitext(urlparse(src).path)[1].lower() or '.bin'
    raw = f'{OUT}/{safe}_{idx}{ext}'
    open(raw, 'wb').write(blob)

    png = to_png(raw)
    if not png:
        return None
    try:
        from PIL import Image
        w, h = Image.open(png).size
    except Exception:
        return None
    if w < MIN_WIDTH:
        return None

    sections = {}
    if raw.lower().endswith('.pdf'):
        try:
            sections = pdf_trace.trace(raw)['sections']
        except Exception:
            sections = {}
    return {'url': url, 'document': src, 'raw': raw, 'png': png,
            'size': [w, h], 'sections': sections}


def main():
    os.makedirs(OUT, exist_ok=True)
    by_team = {}
    for line in open(CANDS):
        p = line.strip().split('|')
        if len(p) >= 3 and p[2].startswith('http'):
            by_team.setdefault((p[0], p[1]), []).append(p[2])

    done = {}
    if os.path.exists(RESULT):
        for r in json.load(open(RESULT)):
            done[r['team']] = r
    results = list(done.values())

    for n, ((team, venue), urls) in enumerate(by_team.items(), 1):
        if team in done:
            continue
        best = None
        for i, u in enumerate(urls[:5]):
            time.sleep(random.uniform(*PAUSE))
            try:
                got = try_url(team, u, i)
            except Exception as e:
                print(f'      {team}: {u[:60]} -> {e}', flush=True)
                continue
            if not got:
                continue
            # the objective itself decides, not the filename
            if best is None or len(got['sections']) > len(best['sections']):
                best = got
            if len(best['sections']) >= 20:
                break

        rec = {'team': team, 'venue': venue, 'tried': len(urls[:5])}
        if best:
            rec.update({'status': 'ok', **best})
            print(f'{n:3} OK {team:18} {os.path.basename(best["png"]):26} '
                  f'{best["size"][0]}x{best["size"][1]} '
                  f'sections={len(best["sections"])}', flush=True)
        else:
            rec['status'] = 'nothing usable'
            print(f'{n:3} -- {team:18} nothing usable from {len(urls[:5])} urls',
                  flush=True)
        results.append(rec)
        json.dump(results, open(RESULT, 'w'), indent=1)

    ok = [r for r in results if r.get('status') == 'ok']
    traced = [r for r in ok if r.get('sections')]
    print(f'\n{len(ok)} charts, {len(traced)} with a text layer, '
          f'{sum(len(r["sections"]) for r in traced)} sections -- ALL UNVERIFIED',
          flush=True)


if __name__ == '__main__':
    main()
