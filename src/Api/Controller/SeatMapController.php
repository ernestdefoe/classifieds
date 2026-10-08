<?php

/*
 * This file is part of ramon/classifieds.
 */

namespace Flarum\Classifieds\Api\Controller;

use Flarum\Classifieds\Listing;
use Flarum\Classifieds\SeatMap;
use Flarum\Foundation\Paths;
use Flarum\Http\Exception\RouteNotFoundException;
use Flarum\Http\RequestUtil;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * GET    /api/classifieds/seatmaps/{id}   → the chart WITH its traced sections
 * PATCH  /api/classifieds/seatmaps/{id}   → save the tracing (and the title)
 * DELETE /api/classifieds/seatmaps/{id}   → remove it.
 */
class SeatMapController implements RequestHandlerInterface
{
    public function __construct(protected Paths $paths)
    {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        RequestUtil::getActor($request)->assertAdmin();

        $map = SeatMap::query()->find((int) ($request->getQueryParams()['id'] ?? 0));

        if (! $map) {
            throw new RouteNotFoundException();
        }

        return match (strtoupper($request->getMethod())) {
            'PATCH' => $this->update($request, $map),
            'DELETE' => $this->destroy($map),
            default => $this->show($map),
        };
    }

    protected function show(SeatMap $map): JsonResponse
    {
        return new JsonResponse(['data' => $map->toSummary() + [
            'sections' => (object) (is_array($map->sections) ? $map->sections : []),
        ]]);
    }

    protected function update(ServerRequestInterface $request, SeatMap $map): JsonResponse
    {
        $body = (array) $request->getParsedBody();

        if (isset($body['title'])) {
            $title = trim((string) $body['title']);

            if ($title === '') {
                return new JsonResponse(['error' => 'needs_title'], 422);
            }

            $map->title = $title;
        }

        if (array_key_exists('sections', $body)) {
            $sections = $body['sections'];

            // Accepted as JSON text too, because a multipart form cannot send
            // a nested object and the tracer posts one either way.
            if (is_string($sections)) {
                $sections = json_decode($sections, true);
            }

            if (! is_array($sections)) {
                return new JsonResponse(['error' => 'bad_payload'], 422);
            }

            $map->sections = $this->clean($sections);
        }

        $map->save();

        return new JsonResponse(['data' => $map->toSummary() + [
            'sections' => (object) (is_array($map->sections) ? $map->sections : []),
        ]]);
    }

    /**
     * 🚨 Clamped to the image, not merely cast.
     *
     * The coordinates arrive from a browser, so a value outside 0..1 is
     * reachable from a mis-drag or a crafted request — and it would place a
     * star outside the picture, where it reads as a broken layout rather than
     * as bad data.
     */
    protected function clean(array $sections): array
    {
        $clean = [];

        foreach ($sections as $name => $point) {
            $name = trim((string) $name);

            if ($name === '' || ! is_array($point) || ! isset($point['x'], $point['y'])) {
                continue;
            }

            $clean[$name] = [
                'x' => round(max(0.0, min(1.0, (float) $point['x'])), 4),
                'y' => round(max(0.0, min(1.0, (float) $point['y'])), 4),
            ];
        }

        return $clean;
    }

    protected function destroy(SeatMap $map): JsonResponse
    {
        $path = $map->image_path;

        /*
         * 🚨 The listings are released first, and the row goes before the file.
         *
         * A listing left pointing at a deleted chart would ask the front end
         * for an image that is not there; and if the unlink fails, a listing
         * must not still be bound to a chart whose row has gone.
         */
        Listing::query()->where('seatmap_id', $map->id)->update(['seatmap_id' => null]);

        $map->delete();

        if (filled($path)) {
            @unlink($this->paths->public.'/assets/classifieds/'.basename($path));
        }

        return new JsonResponse(['data' => ['deleted' => true]]);
    }
}
