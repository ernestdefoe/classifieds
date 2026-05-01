<?php

/*
 * This file is part of ramon/classifieds.
 */

namespace Flarum\Classifieds\Event;

use Flarum\Classifieds\Listing;
use Flarum\Discussion\Discussion;
use Flarum\User\User;

class ListingWasReopened
{
    public function __construct(
        public Discussion $discussion,
        public Listing $listing,
        public User $user
    ) {
    }
}
