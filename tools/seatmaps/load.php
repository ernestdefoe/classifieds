<?php
/**
 * Load the charts harvested from Paciolan/eVenue.
 *
 * Where the geometry came from the tenant's own image map it is exact — but it
 * is exact *for the eVenue picture*. It cannot be merged into a chart drawn
 * from a different image: the labels would land in the wrong parts of the
 * ground, which is the one failure worse than having no map at all. So a chart
 * is taken whole, picture and coordinates together, or not touched.
 *
 *   create   nothing exists for this ground
 *   fill     a chart exists but was never traced — nothing to lose
 *   replace  the existing tracing is thin and this one is far better
 *   keep     anything else, because a hand placement beats a machine's
 *
 * Every replacement is backed up first: some of these were traced by hand on
 * the site, and that work must be recoverable.
 */
$site = require '/var/www/html/site.php';
$site->bootApp();

use Flarum\Classifieds\SeatMap;

$src = '/tmp/evenue';
$manifest = json_decode(file_get_contents("$src/manifest.json"), true);
$dir = '/var/www/html/public/assets/classifieds';
if (! is_dir($dir)) {
    mkdir($dir, 0775, true);
}

$backup = [];
$created = $filled = $replaced = $kept = 0;
$gained = 0;
$notes = [];

foreach ($manifest as $m) {
    $n = count($m['sections']);

    $map = SeatMap::query()->where('title', $m['title'])->first();
    $had = $map ? $map->sectionCount() : -1;

    // An untraced picture is worth adding where we have nothing at all -- a
    // buyer can still see the ground -- but it can never displace a chart.
    if (! $n && $map) {
        $kept++;
        continue;
    }

    if ($had > 0) {
        // Only worth swapping a working chart for a decisively better one.
        $better = $n >= $had * 2 && $n - $had >= 20;
        if (! $better) {
            $kept++;
            $notes[] = "keep    {$m['title']}: has $had, eVenue offers $n";
            continue;
        }
        $backup[] = [
            'title' => $map->title,
            'image_path' => $map->image_path,
            'image_width' => $map->image_width,
            'image_height' => $map->image_height,
            'sections' => $map->sections,
        ];
    }

    $name = 'seatmap_'.bin2hex(random_bytes(8)).'.'.pathinfo($m['file'], PATHINFO_EXTENSION);
    copy("$src/{$m['file']}", "$dir/$name");
    @chmod("$dir/$name", 0664);

    if (! $map) {
        $map = new SeatMap();
        $map->title = $m['title'];
        $created++;
        $notes[] = "create  {$m['title']}: $n sections ({$m['how']})";
    } elseif ($had === 0) {
        $filled++;
        $notes[] = "fill    {$m['title']}: $n sections ({$m['how']})";
    } else {
        $replaced++;
        $notes[] = "replace {$m['title']}: $had -> $n sections ({$m['how']})";
    }

    $map->image_path = $name;
    $map->image_width = $m['width'];
    $map->image_height = $m['height'];
    $map->sections = $m['sections'];
    $map->save();
    $gained += $n - max($had, 0);
}

if ($backup) {
    $f = '/tmp/seatmap-backup-'.date('Ymd-His').'.json';
    file_put_contents($f, json_encode($backup, JSON_PRETTY_PRINT));
    echo 'backed up '.count($backup)." replaced charts to $f\n";
}

sort($notes);
foreach ($notes as $line) {
    echo "  $line\n";
}

echo "\ncreated $created, filled $filled, replaced $replaced, kept $kept (+$gained sections)\n";

$all = SeatMap::query()->get();
echo 'charts: '.$all->count().
     ', traced: '.$all->filter(fn ($x) => $x->sectionCount() > 0)->count().
     ', sections: '.$all->sum(fn ($x) => $x->sectionCount())."\n";
