<?php

/*
 * This file is part of ramon/classifieds.
 */

namespace Flarum\Classifieds\Api\Controller;

use Flarum\Classifieds\SeatMap;
use Flarum\Classifieds\SeatMapImporter;
use Flarum\Classifieds\SeatMapImportException;
use Flarum\Http\RequestUtil;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UploadedFileInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * GET  /api/classifieds/seatmaps   → every chart, with its section count
 * POST /api/classifieds/seatmaps   → add one, from an upload or from a URL
 *
 * Administrators only. Charts are forum-wide furniture, not something a seller
 * creates while writing an advert.
 */
class SeatMapsController implements RequestHandlerInterface
{
    public function __construct(protected SeatMapImporter $importer)
    {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        RequestUtil::getActor($request)->assertAdmin();

        return strtoupper($request->getMethod()) === 'POST'
            ? $this->create($request)
            : $this->index();
    }

    protected function index(): JsonResponse
    {
        $maps = SeatMap::query()->orderBy('title')->get();

        return new JsonResponse(['data' => $maps->map(fn (SeatMap $m) => $m->toSummary())->all()]);
    }

    protected function create(ServerRequestInterface $request): JsonResponse
    {
        $body = (array) $request->getParsedBody();
        $title = trim((string) ($body['title'] ?? ''));

        if ($title === '') {
            return new JsonResponse(['error' => 'needs_title'], 422);
        }

        /** @var UploadedFileInterface|null $file */
        $file = $request->getUploadedFiles()['file'] ?? null;
        $url = trim((string) ($body['url'] ?? ''));

        if (! $file && $url === '') {
            return new JsonResponse(['error' => 'needs_image'], 422);
        }

        try {
            $stored = $file ? $this->importer->fromUpload($file) : $this->importer->fromUrl($url);
        } catch (SeatMapImportException $e) {
            return new JsonResponse(['error' => $e->reason, 'detail' => $e->detail], 422);
        }

        $map = new SeatMap();
        $map->title = $title;
        $map->image_path = $stored['filename'];
        $map->image_width = $stored['width'];
        $map->image_height = $stored['height'];
        $map->sections = [];
        $map->save();

        return new JsonResponse(['data' => $map->toSummary()], 201);
    }
}
