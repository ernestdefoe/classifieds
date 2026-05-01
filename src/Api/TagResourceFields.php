<?php

/*
 * This file is part of ramon/classifieds.
 */

namespace Flarum\Classifieds\Api;

use Flarum\Api\Context;
use Flarum\Api\Schema;
use Flarum\Tags\Tag;

class TagResourceFields
{
    public function __invoke(): array
    {
        return [
            Schema\Boolean::make('isClassifieds')
                ->writable(fn (Tag $tag, Context $context) => $context->getActor()->isAdmin())
                ->get(fn (Tag $tag) => (bool) $tag->is_classifieds)
                ->set(function (Tag $tag, bool $value) {
                    $tag->is_classifieds = $value;
                }),
        ];
    }
}
