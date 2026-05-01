<?php

/*
 * This file is part of ramon/classifieds.
 */

namespace Flarum\Classifieds\Post;

use Carbon\Carbon;
use Flarum\Post\AbstractEventPost;
use Flarum\Post\MergeableInterface;
use Flarum\Post\Post;

class ListingBumpedPost extends AbstractEventPost implements MergeableInterface
{
    public static string $type = 'classifiedsListingBumped';

    public function saveAfter(?Post $previous = null): static
    {
        if ($previous instanceof static && $this->user_id === $previous->user_id) {
            $previous->content = $this->content;
            $previous->save();

            return $previous;
        }

        $this->save();

        return $this;
    }

    public static function reply(int $discussionId, int $userId): static
    {
        $post = new static;

        $post->content = ['bumped' => true];
        $post->created_at = Carbon::now();
        $post->discussion_id = $discussionId;
        $post->user_id = $userId;

        return $post;
    }
}
