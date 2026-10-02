# Seat maps: getting the charts, and the geometry

A seat map is two things: a picture of the ground, and the position of every
section on it. The picture is the easy half.

**Nothing here loads anything without a person looking at it first.** That is
not caution for its own sake — three separate wrong charts got as far as the
loader in one afternoon, and each one would have put a star in the wrong part
of a stadium. A buyer believes a picture over a sentence, so a wrong marker is
worse than none at all: an untraced section just falls back to the seller's own
words, which cost nothing.

## The two sources, and they are not equal

### 1. Paciolan/eVenue image maps — exact, and the only thing that works on these files

`https://<brand>.evenue.net/evenue/linkID=<id>/core/seating_charts.html` serves
the chart *and*, on most tenants, an HTML image map beside it:

```html
<img src="../images/maps/RRS-2014.gif" width="619" height="376"
     alt="Donald W. Reynolds Razorback Stadium" usemap="#razorback">
<map name="razorback">
  <area shape="poly" coords="136,354,130,374,162,373,163,354"
        onMouseOver="mapOverRazorback('101', 'razorback')">
```

The handler's first argument is the section; the polygon is its outline. That
is geometry authored by the ticketing provider — on Arkansas all 58 labels
rendered inside their own sections, first try.

Measured on the same file: **image map 58 sections, OCR 0.** These charts are
~619px wide with 8px section numbers, and no amount of upscaling recovers
detail that was never there. Where a tenant ships a plain picture with no
`<map>`, expect a chart with no stars and say so.

### 2. OCR (`trace.py`) — for charts that are actually legible

Several passes at several scales, and a label is kept only when independent
passes agree on the same place. It works well on large, high-contrast charts
and is at its limit on the 500×500 images most grounds have.

Re-tracing the 39 thinnest charts gained +299 sections and was **not loaded**:
rendering showed about half the new labels were junk — `HLYON` and `HLNOS` are
NORTH and SOUTH read along a curved field edge, `PARS` is PARKING, plus the
colour legend. Raising Tesseract's confidence floor does not separate them
(at 80 the worst junk survives and Notre Dame falls 19 → 0), and neither does
the family rule, because page furniture is numerous and similar-shaped.

## Running it

```bash
export SEATMAP_WORK=/some/work/dir        # harvest.json, charts/, bundle/
python3 evenue_harvest.py                 # needs brands.txt: team|brand|venue
python3 overlay.py                        # draw the sections onto the charts
#   ... LOOK at overlays/, then write decisions.json ...
python3 build_bundle.py                   # bundle/ + manifest.json
#   ... copy bundle into the container as /tmp/evenue ...
php load.php
```

`decisions.json` is deliberately required — `build_bundle.py` skips anything
not listed, so a chart cannot reach the site without a person having seen it:

```json
{"Arkansas": {"accept": true, "geometry": true, "why": "overlay checked"}}
```

## Traps, each of which produced a wrong result

- **🚨 Match the chart by its `alt` text against the venue name, never the
  filename.** Real names are unguessable (`Donald-W.jpg`, `RRS-2014.gif`), and
  matching the *basename* also misses `football_razorback/101.jpg` — where the
  keyword is in the directory, and which is a view-from-seat thumbnail anyway.
- **🚨 Another sport in the path is a hard reject, and a venue-token match does
  not excuse it.** The token is nearly always the school's own name, which
  appears in every file that tenant serves: `michigan_soccer_stadium.gif`
  matched "Michigan Stadium" on exactly that and was filed as the football
  ground twice. `mbb-seatingchart-sfc.gif` reached the loader as Illinois'
  stadium; it is a basketball arena.
- **🚨 "Memorial" is not a stopword.** Several grounds are called plainly
  "Memorial Stadium" and stripping it leaves nothing to match on.
- **🚨 Fractions come from the image's DECLARED width/height**, not the file's
  own pixels. Image-map coordinates live in the rendered box.
- **🚨 Where a section has several `<area>` tags, take the FIRST in document
  order, not the biggest.** Tenants list the real seating block before any broad
  hotspot repeating the label. "Prefer the largest" sounds strictly better, was
  changed after the overlay had been checked and never re-rendered, and put
  Arkansas's section 507 on the Broyles Athletic Center.
- **🚨 Areas landing off the picture mean the map belongs to a chart the tenant
  has since redrawn.** Fresno's fitted inside the box but sat ~5 sections out;
  8 of its 37 shapes overhanging was the only warning. Geometry is dropped
  automatically when that happens.
- **🚨 eVenue answers 403 for both a PerimeterX block and an unknown tenant.**
  `*.evenue.net` is a wildcard, so every brand guess resolves and most are
  nothing. Treating the two alike stopped a whole run on its second school — on
  any 403, re-ask a host known to work. Harvest **sequentially**, 15–25s apart;
  8–14s tripped the throttle at school 43, and an ad-hoc request made by hand
  during a run tripped it again.
- **🚨 Never merge these coordinates into an existing traced chart.** They are
  exact *for the eVenue picture*. `load.php` creates where nothing exists, fills
  a chart that was never traced, replaces only a decisively better one — backing
  the old one up first — and otherwise leaves it alone.
