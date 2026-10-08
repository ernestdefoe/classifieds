<?php

/*
 * This file is part of ramon/classifieds.
 */

namespace Flarum\Classifieds\Access;

use Carbon\Carbon;
use Flarum\Discussion\Discussion;
use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\User\Access\AbstractPolicy;
use Flarum\User\User;

class DiscussionPolicy extends AbstractPolicy
{
    public function __construct(protected SettingsRepositoryInterface $settings)
    {
    }

    public function markListingSold(User $actor, Discussion $discussion): string|bool|null
    {
        if (! $this->isClassifieds($discussion)) {
            return null;
        }

        if ($actor->id && $actor->id === $discussion->user_id) {
            return $this->allow();
        }

        // Staff who may edit other people's discussions can mark any listing.
        // The `discussion.markListingSold` permission (granted to Members by
        // default) means "mark your OWN listing"; through core's catch-all it
        // used to let any member mark ANY seller's listing sold.
        if ($actor->can('edit', $discussion)) {
            return $this->allow();
        }

        return $actor->isAdmin() ? null : $this->deny();
    }

    public function bumpListing(User $actor, Discussion $discussion): string|bool|null
    {
        if (! $this->isClassifieds($discussion)) {
            return null;
        }

        // A bump tops the listing and adds a note to the thread, so it is
        // rationed: once per cooldown (24h by default, 0 turns it off).
        $hours = (float) $this->settings->get('flarum-classifieds.bump_cooldown_hours', 24);
        $bumpedAt = $discussion->listing?->bumped_at;
        if ($hours > 0 && $bumpedAt && $bumpedAt->gt(Carbon::now()->subMinutes((int) round($hours * 60)))) {
            return $this->deny();
        }

        if ($actor->id && $actor->id === $discussion->user_id && $actor->hasPermission('discussion.bumpListing')) {
            return $this->allow();
        }

        // An explicit no: core's catch-all discussion policy would otherwise
        // grant `discussion.bumpListing` holders this on EVERY listing.
        return $actor->isAdmin() ? null : $this->deny();
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

        // As above: the permission means "edit your OWN listing", not
        // everyone's, so the catch-all must not turn it into that.
        return $this->deny();
    }

    protected function isClassifieds(Discussion $discussion): bool
    {
        if (! $discussion->relationLoaded('tags')) {
            $discussion->load('tags');
        }

        return $discussion->getAttribute('tags')->contains(fn ($tag) => (bool) ($tag->is_classifieds ?? false));
    }
}
