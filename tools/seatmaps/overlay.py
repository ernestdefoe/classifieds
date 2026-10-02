#!/usr/bin/env python3
"""Draw traced sections onto their charts so they can be LOOKED at.

The count is not the check. A chart can gain thirty labels and put half of
them in the wrong ring, and nothing but the render shows that.
"""
import json, os, sys
from PIL import Image, ImageDraw, ImageFont

WORK = os.environ.get('SEATMAP_WORK', os.path.abspath('.'))
OUT = f'{WORK}/overlays'
os.makedirs(OUT, exist_ok=True)

FONT = None
for cand in ('/System/Library/Fonts/Supplemental/Arial Bold.ttf',
             '/System/Library/Fonts/Helvetica.ttc'):
    if os.path.exists(cand):
        FONT = cand
        break


def render(path, sections, out, target=1500):
    im = Image.open(path).convert('RGB')
    s = max(1.0, target / im.width)
    im = im.resize((int(im.width * s), int(im.height * s)), Image.LANCZOS)
    d = ImageDraw.Draw(im, 'RGBA')
    f = ImageFont.truetype(FONT, 17) if FONT else ImageFont.load_default()
    for name, p in sections.items():
        x, y = p['x'] * im.width, p['y'] * im.height
        d.ellipse([x - 13, y - 13, x + 13, y + 13], fill=(255, 255, 255, 210))
        d.text((x, y), str(name), fill=(211, 47, 47), font=f, anchor='mm')
    im.save(out)


if __name__ == '__main__':
    data = json.load(open(f'{WORK}/retrace.json'))
    only = sys.argv[1:] or None
    for r in data:
        if r['now'] <= r['had']:
            continue
        if only and r['title'] not in only:
            continue
        name = ''.join(c if c.isalnum() else '_' for c in r['title'])[:40]
        render(f"{WORK}/charts/{r['path']}", r['sections'], f'{OUT}/{name}.png')
        print(f"  {r['had']:3} -> {r['now']:3}  {name}.png")
