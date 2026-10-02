#!/usr/bin/env python3
"""Build one bundle from both kinds of chart the harvest produced.

Two sources, and they are not equal:
  map -- the tenant's own <area> polygons. Exact, and verified by rendering.
  ocr -- our tracer reading the picture, for tenants that ship a plain image.

Both end up as fractions of the image, so the loader does not care which is
which -- but the manifest records it, because when a star is ever wrong it
matters a great deal which one put it there.
"""
import json, os, shutil, subprocess, sys

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
import trace

# Where the harvester left its results, and where the bundle is written.
WORK = os.environ.get('SEATMAP_WORK', os.path.abspath('.'))
CHARTS = f'{WORK}/charts'
OUT = f'{WORK}/bundle'
MIN = 8                      # fewer shapes than this is a key plan, not a bowl

res = json.load(open(f'{WORK}/harvest.json'))
# Nothing is loaded that has not been looked at. The overlay render is the only
# thing that catches a map authored for a picture the tenant has since redrawn,
# and a glance at the picture is the only thing that catches a basketball arena
# filed as a football ground -- both happened in the first batch.
decisions = json.load(open(f'{WORK}/decisions.json'))
shutil.rmtree(OUT, ignore_errors=True)
os.makedirs(OUT)

manifest, skipped = [], []

for r in sorted(res, key=lambda r: r['team']):
    if r.get('status') != 'ok':
        continue
    d = decisions.get(r['team'])
    if d is None:
        skipped.append((r['team'], 'not reviewed')); continue
    if not d['accept']:
        skipped.append((r['team'], 'rejected: ' + d['why'])); continue
    if not d.get('geometry'):
        r = dict(r, sections={})
    venue = (r.get('venue') or '').strip()
    src = f"{CHARTS}/{os.path.basename(r['saved'])}"
    if not venue:
        skipped.append((r['team'], 'no venue name')); continue
    if not os.path.exists(src):
        skipped.append((r['team'], 'image not downloaded')); continue

    if r.get('sections'):
        secs = {k: {'x': v[0], 'y': v[1]} for k, v in r['sections'].items()}
        how = 'map'
    else:
        try:
            t = trace.trace(src)
        except Exception as e:
            skipped.append((r['team'], f'tracer failed: {e}')); continue
        secs = {k: {'x': v['x'], 'y': v['y']} for k, v in t['sections'].items()}
        how = 'ocr'

    if len(secs) < MIN:
        # A picture with no stars still shows a buyer the ground, so it is
        # worth having where we have nothing -- but it must not overwrite a
        # chart that IS traced, and the loader enforces that.
        if secs:
            skipped.append((r['team'], f'{how}: only {len(secs)}, kept as picture only'))
        secs, how = {}, 'image'

    f = os.path.basename(src)
    shutil.copy(src, f'{OUT}/{f}')
    w, h = r['real']
    manifest.append({'title': venue, 'team': r['team'], 'file': f,
                     'width': w, 'height': h, 'how': how,
                     'source': r.get('url', ''), 'sections': secs})
    print(f'  {len(secs):4} {how:5}  {venue}  ({r["team"]})', flush=True)

json.dump(manifest, open(f'{OUT}/manifest.json', 'w'), indent=1)
from collections import Counter
c = Counter(x['how'] for x in manifest)
print(f'\n{len(manifest)} charts ({c["map"]} from image maps, {c["ocr"]} OCR, '
      f'{c["image"]} picture only), '
      f'{sum(len(x["sections"]) for x in manifest)} sections')
if skipped:
    print('\nnot included:')
    for t, why in skipped:
        print(f'  {t:22} {why}')
