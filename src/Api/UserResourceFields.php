<?php

/*
 * This file is part of ramon/classifieds.
 */

namespace Flarum\Classifieds\Api;

use Flarum\Api\Schema;
use Flarum\Discussion\Discussion;
use Flarum\User\User;

class UserResourceFields
{
    /** Per-request cache to avoid running the COUNT query for the same user multiple times. */
    protected static array $cache = [];

    public function __invoke(): array
    {
        return [
            Schema\Integer::make('classifiedsListingsCount')
                ->get(fn (User $user) => $this->countListings($user)),
        ];
    }

    protected function countListings(User $user): int
    {
        $id = (int) $user->id;
        if (isset(static::$cache[$id])) {
            return static::$cache[$id];
        }

        $count = Discussion::query()
            ->where('user_id', $id)
            ->whereNull('hidden_at')
            ->where('is_private', false)
            ->whereExists(function ($q) {
                $q->select($q->raw(1))
                    ->from('discussion_tag')
                    ->join('tags', 'tags.id', '=', 'discussion_tag.tag_id')
                    ->whereColumn('discussion_tag.discussion_id', 'discussions.id')
                    ->where('tags.is_classifieds', 1);
            })
            ->count();

        return static::$cache[$id] = $count;
    }
}
