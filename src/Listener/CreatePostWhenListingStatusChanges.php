<?php

/*
 * This file is part of ramon/classifieds.
 */

namespace Flarum\Classifieds\Listener;

use Flarum\Classifieds\Event\ListingWasBumped;
use Flarum\Classifieds\Event\ListingWasMarkedSold;
use Flarum\Classifieds\Event\ListingWasReopened;
use Flarum\Classifieds\Listing;
use Flarum\Classifieds\Post\ListingBumpedPost;
use Flarum\Classifieds\Post\ListingStatusChangedPost;
use Flarum\Discussion\Discussion;
use Flarum\User\User;

class CreatePostWhenListingStatusChanges
{
    public static function whenSold(ListingWasMarkedSold $event): void
    {
        static::statusChanged($event->discussion, $event->user, $event->listing->status, Listing::STATUS_ACTIVE);
    }

    public static function whenReopened(ListingWasReopened $event): void
    {
        static::statusChanged($event->discussion, $event->user, Listing::STATUS_ACTIVE, $event->listing->status);
    }

    public static function whenBumped(ListingWasBumped $event): void
    {
        $post = ListingBumpedPost::reply($event->discussion->id, $event->user->id);

        $event->discussion->mergePost($post);
    }

    protected static function statusChanged(Discussion $discussion, User $user, string $newStatus, ?string $previousStatus): void
    {
        $post = ListingStatusChangedPost::reply($discussion->id, $user->id, $newStatus, $previousStatus);

        $discussion->mergePost($post);
    }
}
