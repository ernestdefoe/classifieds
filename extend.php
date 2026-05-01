<?php

/*
 * This file is part of ramon/classifieds.
 */

use Flarum\Api\Endpoint;
use Flarum\Api\Resource;
use Flarum\Classifieds\Access;
use Flarum\Classifieds\Api\Controller\ListingScreenshotsController;
use Flarum\Classifieds\Api\DiscussionResourceFields;
use Flarum\Classifieds\Api\TagResourceFields;
use Flarum\Classifieds\Api\UserResourceFields;
use Flarum\Classifieds\Console\PruneListingsCommand;
use Flarum\Classifieds\Event\ListingWasBumped;
use Flarum\Classifieds\Event\ListingWasMarkedSold;
use Flarum\Classifieds\Event\ListingWasReopened;
use Flarum\Classifieds\Listener\CreatePostWhenListingStatusChanges;
use Flarum\Classifieds\Listener\SyncClassifiedsTagsFromSettings;
use Flarum\Classifieds\Listing;
use Flarum\Classifieds\Post\ListingBumpedPost;
use Flarum\Classifieds\Post\ListingStatusChangedPost;
use Flarum\Discussion\Discussion;
use Flarum\Extend;
use Flarum\Tags\Tag;

return [
    (new Extend\Frontend('forum'))
        ->js(__DIR__.'/js/dist/forum.js')
        ->css(__DIR__.'/less/forum.less'),

    (new Extend\Frontend('admin'))
        ->js(__DIR__.'/js/dist/admin.js')
        ->css(__DIR__.'/less/admin.less'),

    new Extend\Locales(__DIR__.'/locale'),

    (new Extend\Settings())
        ->serializeToForum('classifiedsDefaultCurrency', 'flarum-classifieds.default_currency')
        ->serializeToForum('classifiedsRequireLabel', 'flarum-classifieds.require_label', 'boolval')
        ->serializeToForum('classifiedsRequirePrice', 'flarum-classifieds.require_price', 'boolval')
        ->serializeToForum('classifiedsRequireLocation', 'flarum-classifieds.require_location', 'boolval')
        ->serializeToForum('classifiedsAllowPriceRange', 'flarum-classifieds.allow_price_range', 'boolval')
        ->serializeToForum('classifiedsShowCurrencySymbol', 'flarum-classifieds.show_currency_symbol', 'boolval')
        ->serializeToForum('classifiedsAllowedLabels', 'flarum-classifieds.allowed_labels')
        ->default('flarum-classifieds.default_currency', 'USD')
        ->default('flarum-classifieds.require_label', '1')
        ->default('flarum-classifieds.require_price', '1')
        ->default('flarum-classifieds.require_location', '0')
        ->default('flarum-classifieds.allow_price_range', '1')
        ->default('flarum-classifieds.auto_prune_days', '0')
        ->default('flarum-classifieds.auto_prune_sold', '0')
        ->default('flarum-classifieds.allowed_labels', 'iso,wtb,wts,trade')
        ->default('flarum-classifieds.show_currency_symbol', '1')
        ->default('flarum-classifieds.classifieds_tag_ids', '[]')
        ->serializeToForum('classifiedsTagIds', 'flarum-classifieds.classifieds_tag_ids'),

    (new Extend\Model(Tag::class))
        ->cast('is_classifieds', 'bool'),

    (new Extend\Model(Discussion::class))
        ->hasOne('listing', Listing::class, 'discussion_id'),

    (new Extend\ApiResource(Resource\DiscussionResource::class))
        ->fields(DiscussionResourceFields::class)
        ->endpoint([Endpoint\Show::class, Endpoint\Index::class, Endpoint\Create::class, Endpoint\Update::class], function ($endpoint) {
            return $endpoint->eagerLoad(['listing']);
        }),

    (new Extend\ApiResource(Resource\UserResource::class))
        ->fields(UserResourceFields::class),

    (new Extend\Conditional())
        ->whenExtensionEnabled('flarum-tags', fn () => [
            (new Extend\ApiResource(\Flarum\Tags\Api\Resource\TagResource::class))
                ->fields(TagResourceFields::class),
        ]),

    (new Extend\Policy())
        ->modelPolicy(Discussion::class, Access\DiscussionPolicy::class),

    (new Extend\Post())
        ->type(ListingStatusChangedPost::class)
        ->type(ListingBumpedPost::class),

    (new Extend\Event())
        ->listen(ListingWasMarkedSold::class, [CreatePostWhenListingStatusChanges::class, 'whenSold'])
        ->listen(ListingWasReopened::class, [CreatePostWhenListingStatusChanges::class, 'whenReopened'])
        ->listen(ListingWasBumped::class, [CreatePostWhenListingStatusChanges::class, 'whenBumped'])
        ->listen(\Flarum\Settings\Event\Saved::class, SyncClassifiedsTagsFromSettings::class),

    (new Extend\Console())
        ->command(PruneListingsCommand::class)
        ->schedule(PruneListingsCommand::class, function ($event) {
            $event->daily();
        }),

    (new Extend\Routes('api'))
        ->post(
            '/classifieds/listings/{id:[0-9]+}/screenshots',
            'classifieds.listings.screenshots.add',
            ListingScreenshotsController::class
        )
        ->delete(
            '/classifieds/listings/{id:[0-9]+}/screenshots',
            'classifieds.listings.screenshots.remove',
            ListingScreenshotsController::class
        ),
];
