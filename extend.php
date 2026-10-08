<?php

/*
 * This file is part of ramon/classifieds.
 */

use Flarum\Api\Endpoint;
use Flarum\Api\Resource;
use Flarum\Classifieds\Access;
use Flarum\Classifieds\Api\Controller\ListingScreenshotsController;
use Flarum\Classifieds\Api\Controller\OfferedSeatMapsController;
use Flarum\Classifieds\Api\Controller\SeatMapController;
use Flarum\Classifieds\Api\Controller\SeatMapsController;
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
use Flarum\User\User;

return [
    (new Extend\Frontend('forum'))
        ->js(__DIR__.'/js/dist/forum.js')
        // The landing page and the modals are chunks fetched on first use.
        ->jsDirectory(__DIR__.'/js/dist/forum')
        ->css(__DIR__.'/less/forum.less')
        // 🚨 The landing page was a browser-only route: following a link worked,
        // but opening, refreshing or sharing /classifieds was a 404.
        ->route('/classifieds', 'classifieds'),

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
        ->default('flarum-classifieds.bump_cooldown_hours', '24')
        ->default('flarum-classifieds.auto_prune_days', '0')
        ->default('flarum-classifieds.auto_prune_sold', '0')
        ->default('flarum-classifieds.allowed_labels', 'iso,wtb,wts,trade')
        ->default('flarum-classifieds.show_currency_symbol', '1')
        ->default('flarum-classifieds.classifieds_tag_ids', '[]')
        // Empty means "every classifieds listing offers the seat fields".
        ->default('flarum-classifieds.ticket_tag_ids', '[]')
        ->serializeToForum('classifiedsTicketTagIds', 'flarum-classifieds.ticket_tag_ids')
        ->serializeToForum('classifiedsTagIds', 'flarum-classifieds.classifieds_tag_ids'),

    (new Extend\Model(Tag::class))
        ->cast('is_classifieds', 'bool'),

    (new Extend\Model(Discussion::class))
        ->hasOne('listing', Listing::class, 'discussion_id'),

    // A member's visible classifieds listings. Exists so the seller card's
    // count can be a relation aggregate (one grouped query per response)
    // rather than a COUNT per serialized user — see UserResourceFields.
    (new Extend\Model(User::class))
        ->relationship('classifiedsListings', fn (User $user) => $user
            ->hasMany(Discussion::class, 'user_id')
            ->whereNull('discussions.hidden_at')
            ->where('discussions.is_private', false)
            ->whereExists(function ($q) {
                $q->select($q->raw(1))
                    ->from('discussion_tag')
                    ->join('tags', 'tags.id', '=', 'discussion_tag.tag_id')
                    ->whereColumn('discussion_tag.discussion_id', 'discussions.id')
                    ->where('tags.is_classifieds', 1);
            })),

    (new Extend\ApiResource(Resource\DiscussionResource::class))
        ->fields(DiscussionResourceFields::class)
        ->endpoint([Endpoint\Show::class, Endpoint\Index::class, Endpoint\Create::class, Endpoint\Update::class], function ($endpoint) {
            return $endpoint->eagerLoad(['listing', 'listing.seatMap']);
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
        )
        ->get('/classifieds/seatmaps', 'classifieds.seatmaps.index', SeatMapsController::class)
        ->post('/classifieds/seatmaps', 'classifieds.seatmaps.create', SeatMapsController::class)
        ->get('/classifieds/seatmaps/offered', 'classifieds.seatmaps.offered', OfferedSeatMapsController::class)
        ->get('/classifieds/seatmaps/{id:[0-9]+}', 'classifieds.seatmaps.show', SeatMapController::class)
        ->patch('/classifieds/seatmaps/{id:[0-9]+}', 'classifieds.seatmaps.update', SeatMapController::class)
        ->delete('/classifieds/seatmaps/{id:[0-9]+}', 'classifieds.seatmaps.delete', SeatMapController::class),
];
