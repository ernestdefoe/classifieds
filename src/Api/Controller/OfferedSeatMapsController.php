<?php

/*
 * This file is part of ramon/classifieds.
 */

namespace Flarum\Classifieds\Api\Controller;

use Flarum\Classifieds\SeatMap;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * GET /api/classifieds/seatmaps/offered → the traced charts a seller may pick.
 *
 * Fetched by the composer's listing fields and the edit-listing modal when
 * they open. The list used to ride in the forum payload of every page.
 */
class OfferedSeatMapsController implements RequestHandlerInterface
{
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return new JsonResponse(['data' => SeatMap::offered()]);
    }
}
