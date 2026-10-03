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

### 2. The PDF text layer (`pdf_trace.py`) — try this BEFORE OCR, always

Schools publish their charts as vector PDFs drawn in Illustrator, and the
section names in them are usually real text. `pdftotext -bbox` gives the exact
box of every label — the same quality of answer as an image map, for the same
reason: nobody guessed it.

Measured on Penn State's Beaver Stadium: **OCR 0 sections, text layer 113.**
Georgia Tech 46, the LA Memorial Coliseum 56, all placed exactly.

What it buys over OCR is precision, not recall. OCR returns `HLYON` for NORTH
and `JOWS` for ROWS, and no stop list can anticipate a misreading; here the
words are exact, so a short list of chart furniture removes them. It also
gives something OCR never can — **word order**: "GATE F" leaves an `F` sitting
exactly where a section label would be, and the preceding word is what tells
them apart.

🚨 **But only when that word is genuinely adjacent.** Reading order in a PDF is
not spatial: USC's section 322 follows an unrelated "GATE" from the other side
of the page, and a bare preceding-word test silently discarded real sections.
Compare the boxes, not the sequence.

🚨 **A chart can be part text and part outlines.** USC's 104, 105, 106 and 110
are vector shapes with no text behind them, so they simply are not there.
Expect gaps on mixed charts rather than assuming the extraction failed.

### 3. OCR (`trace.py`) — for charts that are actually legible

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
python3 school_harvest.py                 # schools' own sites: team|brand|venue
python3 chart_from_urls.py                # or feed URLs: team|venue|url
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
- **🚨 Crawling schools' sites found NOTHING usable — 70 schools, 33 downloads,
  zero charts.** What it actually collected: a volleyball match-notes PDF
  (scored 143 "sections"), a soccer box score (147), a men's golf statistics
  table (86), a cross-country results sheet, media guides and a compliance
  policy. Every chart on the site came from a direct link or a targeted
  search instead. Three guards now refuse those documents — see below — but
  the lesson is that page-scraping an athletics site is not a route.
- **🚨 "Whichever traces best" is fooled by prose.** Clemson's seating-chart
  *page* led to a compliance PDF, and HAZING, FRAUD, TITLE, IX and SEXUAL are
  all section-shaped — it scored 38 "sections". The giveaway is that they are
  WORDS, where a real chart's labels are codes, so a document whose labels are
  >40% dictionary words is refused whole. This test works on a text layer and
  NOT on OCR, whose junk (`HLYON`, `JOWS`) is in no dictionary.
- **🚨 ...and a box score is fooled by neither.** A volleyball stats sheet's
  junk is NUMBERS, so it passes the dictionary test. Two more shapes separate
  it: a real chart carries **223-433 words** where those documents carried
  **1005-1196**, and a chart scatters its labels round an oval where a table
  lines them up — charts put **7-40%** of labels on a baseline shared by five
  or more, tables **73-96%**. All three guards together pass every real chart
  and refuse every document measured.
- **🚨 "Judge by the answer" can still pick a document over the real chart.**
  Tulsa publishes `Football-Map.png` beside a cross-country results PDF. The
  chart is a raster image with no text layer and scored 0; the results sheet
  scored 36, so it won. When a candidate list mixes images and PDFs, check
  whether a plainly-named chart image was passed over.
- **🚨 Do not guess which document is the chart — judge by the answer.**
  `chart_from_urls.py` fetches several candidates and keeps whichever traces
  best. A parking map scores 0 and loses on its own merits, with no rule
  needed to describe it. Picking Penn State's by filename took one with no
  text layer at all.
- **🚨 Score what a file IS above what format it is.** Iowa publishes
  `kinnick-seating-map.png` beside three opaquely-named PDFs, and weighting
  `.pdf` above the keyword ranked the unknowns first.
- **🚨 Send `--compressed`.** Without it curl hands back gzip and every page
  scores as binary noise: two schools reported "nothing that scored as a
  chart" with the links right there in the markup.
- **🚨 Never merge these coordinates into an existing traced chart.** They are
  exact *for the eVenue picture*. `load.php` creates where nothing exists, fills
  a chart that was never traced, replaces only a decisively better one — backing
  the old one up first — and otherwise leaves it alone.
