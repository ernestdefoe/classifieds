<?php

/*
 * This file is part of ramon/classifieds.
 */

namespace Flarum\Classifieds\Access;

use Flarum\Discussion\Discussion;
use Flarum\User\Access\AbstractPolicy;
use Flarum\User\User;

class DiscussionPolicy extends AbstractPolicy
{
    public function markListingSold(User $actor, Discussion $discussion): string|bool|null
    {
        if (! $this->isClassifieds($discussion)) {
            return null;
        }

        if ($actor->id && $actor->id === $discussion->user_id) {
            return $this->allow();
        }

        if ($actor->hasPermission('discussion.markListingSold')) {
            return $this->allow();
        }

        return null;
    }

    public function bumpListing(User $actor, Discussion $discussion): string|bool|null
    {
        if (! $this->isClassifieds($discussion)) {
            return null;
        }

        if ($actor->id && $actor->id === $discussion->user_id && $actor->hasPermission('discussion.bumpListing')) {
            return $this->allow();
        }

        return null;
    }

    public function editListing(User $actor, Discussion $discussion): string|bool|null
    {
        if (! $this->isClassifieds($discussion)) {
            return null;
        }

        if ($actor->id && $actor->id === $discussion->user_id && $actor->hasPermission('discussion.editListing')) {
            return $this->allow();
        }

        if ($actor->can('edit', $discussion)) {
            return $this->allow();
        }

        return null;
    }

    protected function isClassifieds(Discussion $discussion): bool
    {
        if (! $discussion->relationLoaded('tags')) {
            $discussion->load('tags');
        }

        return $discussion->tags->contains(fn ($tag) => (bool) ($tag->is_classifieds ?? false));
    }
}
