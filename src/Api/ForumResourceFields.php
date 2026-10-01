<?php

/*
 * This file is part of ramon/classifieds.
 */

namespace Flarum\Classifieds\Api;

use Flarum\Api\Schema;
use Flarum\Classifieds\SeatMap;

class ForumResourceFields
{
    public function __invoke(): array
    {
        return [
            /*
             * The stadium charts a seller may pick from in the composer.
             *
             * 🚨 Only charts that have actually been traced are offered.
             *
             * A ground in the list whose sections were never marked produces an
             * advert that promises a seat chart and then shows the seller's
             * text instead: the control would be there, worded, styled and
             * doing nothing. An admin who uploads a chart has to trace it
             * before anyone can choose it.
             */
            Schema\Arr::make('classifiedsSeatMaps')
                ->get(function () {
                    return SeatMap::query()
                        ->orderBy('title')
                        ->get()
                        ->filter(fn (SeatMap $m) => $m->sectionCount() > 0 && filled($m->image_path))
                        ->map(fn (SeatMap $m) => $m->toSummary())
                        ->values()
                        ->all();
                }),
        ];
    }
}
