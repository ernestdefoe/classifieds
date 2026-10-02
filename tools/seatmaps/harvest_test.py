#!/usr/bin/env python3
"""The cases that actually went wrong, so they cannot go wrong again.

Two of these shipped: michigan_soccer_stadium.gif was filed as Michigan
Stadium (twice -- the first fix was waived by a match on the school's own
name) and mbb-seatingchart-sfc.gif as Gies Memorial Stadium. Both were caught
by looking at the picture, not by any check in the code. These are that check.
"""
import importlib.util, os, sys

HERE = os.path.dirname(os.path.abspath(__file__))
spec = importlib.util.spec_from_file_location('h', f'{HERE}/evenue_harvest.py')
h = importlib.util.module_from_spec(spec)
spec.loader.exec_module(h)


def img(src, alt='', w=500, ht=195, usemap=''):
    return {'src': src, 'alt': alt, 'id': '', 'usemap': usemap,
            'w': w, 'h': ht, 'at': 0}


CASES = [
    ('Michigan Stadium', [img('../images/maps/michigan_soccer_stadium.gif')],
     None, 'soccer ground must be refused'),
    ('Gies Memorial Stadium', [img('../images/maps/mbb-seatingchart-sfc.gif')],
     None, 'mbb arena must be refused'),
    ('Michigan Stadium',
     [img('../images/maps/michigan_soccer_stadium.gif'),
      img('../images/maps/michigan_stadium_fb.gif', 'Michigan Stadium', 900, 600)],
     'michigan_stadium_fb.gif', 'the real ground must win'),
    ('Jordan-Hare Stadium',
     [img('../images/maps/JordanHare-Football.gif', '', 600, 381)],
     'JordanHare-Football.gif', 'football in the name is enough'),
    ('Donald W. Reynolds Razorback Stadium',
     [img('../images/maps/football_razorback/101.jpg', '', 200, 150),
      img('../images/maps/RRS-2014.gif', 'Donald W. Reynolds Razorback Stadium',
          619, 376, 'razorback')],
     'RRS-2014.gif', 'the mapped chart beats the seat-view thumb'),
    ('Memorial Stadium',
     [img('../images/maps/memorial_stadium.gif', 'Memorial Stadium', 700, 400)],
     'memorial_stadium.gif', '"Memorial" alone must still match'),
    ('Memorial Stadium',
     [img('../images/maps/memorial_coliseum_bb.gif', 'Memorial Coliseum', 700, 400)],
     None, 'a basketball coliseum must not pass as Memorial Stadium'),
]

MAP_PAGE = """
<map name="razorback">
 <area shape="poly" coords="0,0,10,0,10,10,0,10" onMouseOver="mapOverRazorback('101','razorback')">
 <area shape="poly" coords="20,0,40,0,40,20,20,20" onMouseOver="mapOverRazorback('102','razorback')">
 <area shape="poly" coords="0,0,400,0,400,400,0,400" onMouseOver="mapOverRazorback('102','razorback')">
 <area shape="rect" coords="50,50,70,70" onMouseOut="mapOverRazorback('none','razorback')">
</map>
<map id="calmemorial">
 <area shape="rect" coords="0,0,100,50" alt="Section KK">
 <area shape="circle" coords="200,100,20" title="12">
</map>"""

MAP_EXPECT = [
    (('razorback', '101'), (5.0, 5.0), 'polygon centroid'),
    # 102 is drawn twice. The FIRST one wins: a tenant lists the real seating
    # block before any broad hotspot that repeats the label, and preferring the
    # larger shape put Arkansas's 507 on a building.
    (('razorback', '102'), (30.0, 10.0), 'first area in document order wins'),
    (('calmemorial', 'KK'), (50.0, 25.0), 'rect centre, section from alt'),
    (('calmemorial', '12'), (200.0, 100.0), 'circle centre, section from title'),
]


def map_checks():
    """The <map> parser. id= matters: California declares a usemap we could
    find no other way, and 'none' from an onMouseOut is not a section."""
    m = h.maps_in(MAP_PAGE)
    out = []
    out.append((set(m) == {'razorback', 'calmemorial'}, 'both maps found (name= and id=)'))
    out.append(('none' not in m.get('razorback', {}), "onMouseOut 'none' is not a section"))
    for (mp, sec), want, why in MAP_EXPECT:
        out.append((m.get(mp, {}).get(sec) == want, why))
    return out


if __name__ == '__main__':
    page = '<html>' + ' ' * 600
    bad = 0
    for ok, why in map_checks():
        bad += not ok
        print(f'{"PASS" if ok else "FAIL"}  {why}')
    for venue, imgs, want, why in CASES:
        got = h.pick(page, imgs, venue, {'razorback': {'101': (1, 1)}})
        name = got[1]['src'].split('/')[-1] if got else None
        ok = name == want
        bad += not ok
        print(f'{"PASS" if ok else "FAIL"}  {why}')
        if not ok:
            print(f'        got {name!r}, wanted {want!r}')
    total = len(CASES) + len(MAP_EXPECT) + 2
    print(f'\n{total - bad}/{total} passed')
    sys.exit(1 if bad else 0)
