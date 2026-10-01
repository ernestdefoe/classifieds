<?php

/*
 * This file is part of ramon/classifieds.
 */

namespace Flarum\Classifieds;

use Flarum\Foundation\Paths;
use GuzzleHttp\Client;
use Psr\Http\Message\UploadedFileInterface;
use Symfony\Component\Process\Exception\ExceptionInterface as ProcessException;
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
        } catch (ProcessException|\Throwable $e) {
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

        $ips = @gethostbynamel($parts['host']) ?: [];

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
