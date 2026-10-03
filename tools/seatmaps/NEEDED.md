# Seating charts — final state

**95 charts, 89 a seller can pick, 3333 sections.**

Started the day at 68 / 61 / 2,091.

## How a chart gets found

```
"pricing map" OR "seating chart" pdf site:<school>.com <venue> football
```

A school **ticket pricing map** drawn in Illustrator has live text and yields
30–110 exact sections via `pdf_trace.py`. A gameday or facilities map is
usually outlined artwork and yields nothing. OCR (`trace3.py`, tiled) only
works when the image is large — Minnesota at 4109px worked, most do not.

## Checked and ruled out (46)

Re-hunting these is wasted effort unless the school republishes.

| School | Ground | Why |
|---|---|---|
| Air Force | Falcon Stadium | only a 1071x722 image, too small |
| Akron | InfoCision Stadium | ticket page 404s |
| Arizona | Arizona Stadium | chart numbers are outlines; only the price table has text |
| Boston College | Alumni Stadium (Chestnut Hill, MA) | chart found, no text layer, OCR unusable |
| Bowling Green | Doyt L. Perry Stadium | no chart asset reachable |
| Buffalo | Broadview Stadium | no chart asset reachable |
| California | California Memorial Stadium | chart found, no text layer, OCR unusable |
| Coastal | Brooks Stadium (SC) | ticket page 404s |
| Duke | Wallace Wade Stadium | chart found, no text layer, OCR unusable |
| East Carolina | Dowdy-Ficklen Stadium | interactive viewer only |
| FIU | Pitbull Stadium | no chart asset reachable |
| Fresno St | Valley Children's Stadium | interactive viewer only |
| GA Southern | Allen E. Paulson Stadium | only a parking map |
| Hawai'i | Clarence T.C. Ching Athletics Complex | no chart document published |
| Iowa | Kinnick Stadium | chart found, no text layer, OCR unusable |
| Jax State | AmFirst Stadium | ticket page 404s |
| Kansas | David Booth Kansas Memorial Stadium | only a gate-access map, not a seating chart |
| Kennesaw St | Walens Family Field at Fifth Third Stadium | no chart asset reachable |
| Kent State | Zoeller Field at Dix Stadium | no chart asset reachable |
| LSU | Tiger Stadium (LA) | interactive 3D viewer only |
| MTSU | Johnny "Red" Floyd Stadium | no chart asset reachable |
| Miami OH | Yager Stadium | no chart asset reachable |
| Michigan St | Spartan Stadium | chart found, no text layer, OCR unusable |
| Missouri | Memorial Stadium | the indexed seating-chart PDF 404s |
| Missouri St | Robert W. Plaster Stadium | ticket page 404s |
| N Illinois | Huskie Stadium | no chart asset reachable |
| Nebraska | Memorial Stadium (Lincoln, NE) | section numbers are vector outlines; only gates/rows/suites are text |
| Nevada | Mackay Stadium | good map, OCR returns junk |
| New Mexico | University Stadium (NM) | no chart document published |
| New Mexico St | Aggie Memorial Stadium | no chart asset reachable |
| North Carolina | Kenan Stadium | chart found, no text layer, OCR gives ~7 |
| Old Dominion | S.B. Ballard Stadium | chart found, no text layer, OCR junk |
| Ole Miss | Vaught-Hemingway Stadium | no chart document published |
| Rutgers | SHI Stadium | chart found, no text layer |
| Sam Houston | Elliott T. Bowers Stadium | no chart asset reachable |
| South Alabama | Hancock Whitney Stadium | no chart asset reachable |
| Southern Miss | M. M. Roberts Stadium | only a parking map |
| Texas A&M | Kyle Field | only a 2019 priority map that will not fetch |
| Toledo | Glass Bowl | no chart asset reachable |
| UCLA | Rose Bowl | season-ticket PDF is outlined artwork |
| UL Monroe | Malone Stadium | no chart asset reachable |
| UTEP | Sun Bowl | no chart document published |
| Vanderbilt | FirstBank Stadium | interactive 3D viewer only |
| Virginia | Scott Stadium | only a lacrosse pricing map |
| Wake Forest | Allegacy Federal Credit Union Stadium | no chart asset reachable |
| West Virginia | Milan Puskar Stadium | chart found, no text layer |

## What is left

These grounds publish nothing machine-readable. The admin tracer handles them
at roughly 2–3 seconds per section — a few minutes per ground — and is worth
spending only on the venues your members actually sell tickets for.
