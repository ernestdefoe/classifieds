<?php

/*
 * This file is part of ramon/classifieds.
 */

namespace Flarum\Classifieds;

use Flarum\Foundation\Paths;
use GuzzleHttp\Client;
use Psr\Http\Message\UploadedFileInterface;
use Symfony\Component\Process\Process;

/**
 * Gets a stadium chart onto this server, from an upload or from a URL.
 *
 * 🚨 Paste a URL, never crawl for one.
 *
 * Athletics sites share no layout, no naming and no standard place for a
 * seating chart, so anything that guessed would fetch the wrong picture often
 * enough to be worse than useless — and a wrong stadium chart is a confidently
 * wrong answer, which is the one thing this whole feature exists to avoid.
 * This was measured, not assumed: Ohio State's chart URL serves 1.7MB of HTML
 * rather than the PDF it names, and the largest image on Alabama's facility
 * page is a photograph of the ground. Both would have been filed as the chart.
 *
 * Finding the right chart takes a person about ten seconds. This removes
 * everything after that.
 */
class SeatMapImporter
{
    /** Anything smaller than this is a logo or a thumbnail, not a chart. */
    public const MIN_EDGE = 200;

    public const MAX_BYTES = 12 * 1024 * 1024;

    public const TYPES = [
        IMAGETYPE_PNG => 'png',
        IMAGETYPE_JPEG => 'jpg',
        IMAGETYPE_GIF => 'gif',
        IMAGETYPE_WEBP => 'webp',
    ];

    public function __construct(protected Paths $paths)
    {
    }

    /**
     * Stores an uploaded chart and returns its filename and dimensions.
     *
     * @throws SeatMapImportException
     *
     * @return array{filename: string, width: int, height: int}
     */
    public function fromUpload(UploadedFileInterface $file): array
    {
        if ($file->getError() !== UPLOAD_ERR_OK) {
            throw new SeatMapImportException('upload_failed');
        }

        if ($file->getSize() > self::MAX_BYTES) {
            throw new SeatMapImportException('file_too_large');
        }

        $tmp = $this->tempFile();
        $stream = $file->getStream();
        $stream->rewind();
        file_put_contents($tmp, $stream->getContents());

        return $this->store($tmp);
    }

    /**
     * Downloads a chart from a public address and stores it.
     *
     * @throws SeatMapImportException
     *
     * @return array{filename: string, width: int, height: int}
     */
    public function fromUrl(string $url): array
    {
        $this->assertFetchable($url);

        try {
            $response = (new Client())->get($url, [
                'timeout' => 15,
                'headers' => ['User-Agent' => 'Mozilla/5.0 (compatible; Classifieds seat-map import)'],
                /*
                 * 🚨 Redirects are followed but bounded, and the scheme is
                 * pinned. An open redirect on a public site is otherwise a
                 * clean route back to this server's own network — the guard
                 * above only ever saw the first hop.
                 */
                'allow_redirects' => [
                    'max' => 3,
                    'strict' => true,
                    'referer' => false,
                    'protocols' => ['http', 'https'],
                    'on_redirect' => function ($request, $response, $uri) {
                        $this->assertFetchable((string) $uri);
                    },
                ],
                // Bounded before anything is written: a URL can serve anything.
                'stream' => false,
            ]);
        } catch (\Throwable $e) {
            throw new SeatMapImportException('fetch_failed', $e->getMessage());
        }

        $bytes = (string) $response->getBody();

        if ($bytes === '' || strlen($bytes) > self::MAX_BYTES) {
            throw new SeatMapImportException('fetch_failed', 'empty or oversized response');
        }

        /*
         * 🚨 One hop through a viewer page, because that is what a school's
         * seating-chart link actually is.
         *
         * Measured: gopsusports.com/documents/<uuid>.pdf answers 200 with
         * `text/html` and 400KB of page — from curl AND from a real browser, so
         * it is not a bot wall, it is how SidearmSports publishes documents.
         * The chart itself was on storage.googleapis.com, one link deeper, and
         * it converted to a perfectly good 1774x1411 diagram. Most college
         * athletics sites run SidearmSports, so without this hop the importer
         * refuses the commonest source of charts there is, and does it with a
         * "not an image" message that blames the operator's URL.
         */
        if ($this->looksLikeHtml($bytes)) {
            $asset = $this->documentUrlWithin($bytes, $url);

            if ($asset === null) {
                throw new SeatMapImportException('page_not_a_chart');
            }

            // The same guard as the first hop: a page can name any address.
            $this->assertFetchable($asset);

            try {
                $bytes = (string) (new Client())->get($asset, [
                    'timeout' => 20,
                    'headers' => ['User-Agent' => 'Mozilla/5.0 (compatible; Classifieds seat-map import)'],
                    'allow_redirects' => ['max' => 2, 'strict' => true, 'referer' => false, 'protocols' => ['http', 'https']],
                ])->getBody();
            } catch (\Throwable $e) {
                throw new SeatMapImportException('fetch_failed', $e->getMessage());
            }

            if ($bytes === '' || strlen($bytes) > self::MAX_BYTES || $this->looksLikeHtml($bytes)) {
                // One hop only. A viewer page that leads to another viewer page
                // is a site this cannot read, and chasing it would be a crawler.
                throw new SeatMapImportException('page_not_a_chart');
            }
        }

        $tmp = $this->tempFile();
        file_put_contents($tmp, $bytes);

        /*
         * 🚨 PDFs are converted, not rejected.
         *
         * Schools publish their seating charts as PDFs far more often than as
         * images — Ohio State and Alabama both do. Refusing them would turn
         * this importer away from most of the files it exists to fetch.
         */
        if (strncmp($bytes, '%PDF', 4) === 0) {
            $tmp = $this->pdfToPng($tmp);
        }

        return $this->store($tmp);
    }

    protected function looksLikeHtml(string $bytes): bool
    {
        $head = ltrim(substr($bytes, 0, 512));

        return stripos($head, '<!doctype') === 0 || stripos($head, '<html') === 0 || stripos($head, '<?xml') === 0;
    }

    /**
     * The document a viewer page is actually showing, or null.
     *
     * 🚨 Scored, not "the first one" and not "the biggest".
     *
     * A page carries dozens of images — logos, sponsors, social icons, an
     * open-graph preview. Picking by position or by size is how a harvester
     * ends up filing a photograph of the ground as its seating chart, which is
     * exactly the confidently-wrong answer this feature exists to avoid. So a
     * candidate has to look like a document (a PDF, or a file whose name says
     * seat/map/chart), and anything that smells like site furniture is dropped.
     */
    protected function documentUrlWithin(string $html, string $pageUrl): ?string
    {
        /*
         * 🚨 Backslash-escaped slashes are undone first. These pages carry the
         * real address inside embedded JSON as `https:\/\/storage...`, so
         * matching the raw HTML finds the decorative <img> tags and misses the
         * document the page exists to show.
         */
        $html = str_replace('\\/', '/', $html);

        if (! preg_match_all('~https?://[^\s"\'<>]+\.(?:pdf|png|jpe?g|gif|webp)~i', $html, $m)) {
            return null;
        }

        $best = null;
        $bestScore = 0;

        foreach (array_unique($m[0]) as $candidate) {
            // The page's own address is not the document it is displaying.
            if (strcasecmp($candidate, $pageUrl) === 0) {
                continue;
            }

            $path = strtolower((string) parse_url($candidate, PHP_URL_PATH));

            // Site furniture, never a seating chart.
            if (preg_match('~(logo|icon|favicon|sponsor|avatar|banner|header|footer|thumb|social|placeholder)~', $path)) {
                continue;
            }

            $score = 0;

            // A PDF on an athletics site is nearly always the document itself.
            if (str_ends_with($path, '.pdf')) {
                $score += 10;
            }

            if (preg_match('~(seat|map|chart|stadium|diagram)~', $path)) {
                $score += 6;
            }

            // Sidearm and friends serve the real file from object storage.
            $host = strtolower((string) parse_url($candidate, PHP_URL_HOST));

            if (preg_match('~(storage\.googleapis\.com|s3[.-][a-z0-9-]*amazonaws\.com|cloudfront\.net|blob\.core\.windows\.net)~', $host)) {
                $score += 4;
            }

            // An imgproxy/resizer URL is a thumbnail of something else.
            if (preg_match('~(imgproxy|/rs:fit|/resize|[?&]w=\d+)~', $candidate)) {
                $score -= 6;
            }

            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $candidate;
            }
        }

        // 🚨 A weak best is no answer. Returning the least-bad image would hand
        // back a sponsor banner with full confidence.
        return $bestScore >= 6 ? $best : null;
    }

    /**
     * @throws SeatMapImportException
     *
     * @return array{filename: string, width: int, height: int}
     */
    protected function store(string $tmp): array
    {
        /*
         * 🚨 Checked as an image by its CONTENT, not by its extension. This
         * writes into a publicly served directory, so a file that merely ends
         * in .png is not good enough.
         */
        $info = @getimagesize($tmp);
        $ext = $info ? (self::TYPES[$info[2]] ?? null) : null;

        if (! $info || ! $ext) {
            @unlink($tmp);

            throw new SeatMapImportException('not_an_image');
        }

        if ($info[0] < self::MIN_EDGE || $info[1] < self::MIN_EDGE) {
            @unlink($tmp);

            throw new SeatMapImportException('too_small');
        }

        $dir = $this->paths->public.'/assets/classifieds';

        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        // Generated server side: no component of the name comes from the
        // client, so there is nothing to traverse or overwrite with.
        $filename = 'seatmap_'.bin2hex(random_bytes(8)).'.'.$ext;

        if (! @rename($tmp, $dir.'/'.$filename)) {
            @copy($tmp, $dir.'/'.$filename);
            @unlink($tmp);
        }

        @chmod($dir.'/'.$filename, 0664);

        return ['filename' => $filename, 'width' => (int) $info[0], 'height' => (int) $info[1]];
    }

    /**
     * The first page of a PDF rendered to a PNG.
     *
     * 🚨 150dpi, not the 72dpi default. A seating chart is mostly thin lines
     * and small section numbers; at 72dpi the numbers are unreadable, which
     * makes the chart useless for the one job it has.
     *
     * 🚨 Only the first page. These documents are one chart followed by pricing
     * tables nobody needs on an advert.
     *
     * @throws SeatMapImportException
     */
    protected function pdfToPng(string $pdfPath): string
    {
        $out = $pdfPath.'-page';

        // Arguments as an array: the path is generated, but it is still never
        // interpolated into a shell string.
        $process = new Process(['pdftoppm', '-png', '-r', '150', '-f', '1', '-l', '1', $pdfPath, $out]);
        $process->setTimeout(30);

        try {
            $process->run();
        } catch (\Throwable $e) {
            @unlink($pdfPath);

            throw new SeatMapImportException('pdf_no_poppler');
        }

        @unlink($pdfPath);

        foreach ([$out.'-1.png', $out.'-01.png', $out.'-001.png'] as $candidate) {
            if (file_exists($candidate)) {
                return $candidate;
            }
        }

        /*
         * 🚨 A missing binary and a corrupt PDF are reported differently. Both
         * fail here, but one is fixed by installing poppler-utils in the
         * container and the other by finding a different file — a single
         * "conversion failed" would send an operator hunting the wrong one.
         */
        throw new SeatMapImportException(
            $process->getExitCode() === 127 || ! $this->haveBinary('pdftoppm') ? 'pdf_no_poppler' : 'pdf_failed'
        );
    }

    protected function haveBinary(string $name): bool
    {
        $which = new Process(['sh', '-c', 'command -v '.escapeshellarg($name)]);

        try {
            $which->run();
        } catch (\Throwable $e) {
            return false;
        }

        return trim($which->getOutput()) !== '';
    }

    /**
     * Whether this server should be asked to fetch that address at all.
     *
     * 🚨 This is a request made BY the server, so it can reach things a visitor
     * cannot: the other sites on this box, the Docker network, and cloud
     * metadata endpoints. Only public http(s) addresses are allowed, and every
     * address the hostname resolves to is checked — not just the first.
     *
     * @throws SeatMapImportException
     */
    protected function assertFetchable(string $url): void
    {
        $parts = parse_url($url);

        if (! $parts || empty($parts['host']) || ! in_array($parts['scheme'] ?? '', ['http', 'https'], true)) {
            throw new SeatMapImportException('bad_url');
        }

        // A host written only in digits, dots and hex marks must be a plain
        // dotted quad. gethostbynamel() reads 0177.0.0.1 as decimal (a public
        // 177.x address) while curl reads it as octal (127.0.0.1), so any
        // other spelling of a number is refused rather than second-guessed.
        $host = $parts['host'];
        if (preg_match('/^(0x[0-9a-f]*|[0-9]+)(\.(0x[0-9a-f]*|[0-9]+))*\.?$/i', $host)
            && filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false) {
            throw new SeatMapImportException('bad_url');
        }

        $ips = @gethostbynamel($host) ?: [];

        if (! $ips) {
            throw new SeatMapImportException('bad_url');
        }

        foreach ($ips as $ip) {
            if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                throw new SeatMapImportException('private_url');
            }
        }
    }

    protected function tempFile(): string
    {
        return tempnam(sys_get_temp_dir(), 'seatmap');
    }
}
