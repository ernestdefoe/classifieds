<?php

/*
 * This file is part of ramon/classifieds.
 */

namespace Flarum\Classifieds\Api;

use Carbon\Carbon;
use Flarum\Api\Context;
use Flarum\Api\Schema;
use Flarum\Classifieds\Event\ListingWasBumped;
use Flarum\Classifieds\Event\ListingWasMarkedSold;
use Flarum\Classifieds\Event\ListingWasReopened;
use Flarum\Classifieds\Listing;
use Flarum\Classifieds\ListingValidator;
use Flarum\Discussion\Discussion;
use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\User\User;
use Illuminate\Contracts\Events\Dispatcher;
use WeakMap;

class DiscussionResourceFields
{
    /**
     * Per-request transient stash keyed by Discussion instance.
     * Stored externally so it never leaks into Eloquent's $attributes
     * (which would cause it to be persisted as a column on save).
     *
     * @var WeakMap<Discussion, array{stash: array, registered: bool}>|null
     */
    protected static ?WeakMap $stash = null;

    public function __construct(
        protected SettingsRepositoryInterface $settings,
        protected ListingValidator $validator,
        protected Dispatcher $events,
    ) {
    }

    public function __invoke(): array
    {
        return [
            Schema\Boolean::make('isClassifieds')
                ->get(fn (Discussion $d) => $this->isClassifieds($d)),

            Schema\Boolean::make('canMarkListingSold')
                ->get(fn (Discussion $d, Context $c) => $c->getActor()->can('markListingSold', $d)),

            Schema\Boolean::make('canBumpListing')
                ->get(fn (Discussion $d, Context $c) => $c->getActor()->can('bumpListing', $d)),

            Schema\Boolean::make('canEditListing')
                ->get(fn (Discussion $d, Context $c) => $c->getActor()->can('editListing', $d)),

            Schema\Str::make('listingLabel')
                ->writable(fn (Discussion $d, Context $c) => $this->canWriteListing($d, $c))
                ->nullable()
                ->get(fn (Discussion $d) => $d->listing?->label)
                ->set(fn (Discussion $d, ?string $value, Context $c) => $this->stage($d, $c, ['label' => $this->normalize($value)])),

            Schema\Str::make('listingStatus')
                ->writable(fn (Discussion $d, Context $c) => $this->canChangeStatus($d, $c))
                ->get(fn (Discussion $d) => $d->listing?->status)
                ->set(fn (Discussion $d, string $value, Context $c) => $this->stage($d, $c, ['status' => $this->normalize($value)])),

            Schema\Number::make('listingPrice')
                ->writable(fn (Discussion $d, Context $c) => $this->canWriteListing($d, $c))
                ->nullable()
                ->get(fn (Discussion $d) => $d->listing?->price)
                ->set(fn (Discussion $d, $value, Context $c) => $this->stage($d, $c, ['price' => $this->numeric($value)])),

            Schema\Number::make('listingPriceMax')
                ->writable(fn (Discussion $d, Context $c) => $this->canWriteListing($d, $c))
                ->nullable()
                ->get(fn (Discussion $d) => $d->listing?->price_max)
                ->set(fn (Discussion $d, $value, Context $c) => $this->stage($d, $c, ['price_max' => $this->numeric($value)])),

            Schema\Str::make('listingCurrency')
                ->writable(fn (Discussion $d, Context $c) => $this->canWriteListing($d, $c))
                ->nullable()
                ->get(fn (Discussion $d) => $d->listing?->currency)
                ->set(fn (Discussion $d, ?string $value, Context $c) => $this->stage($d, $c, ['currency' => $this->normalize($value)])),

            Schema\Str::make('listingLocation')
                ->writable(fn (Discussion $d, Context $c) => $this->canWriteListing($d, $c))
                ->nullable()
                ->get(fn (Discussion $d) => $d->listing?->location)
                ->set(fn (Discussion $d, ?string $value, Context $c) => $this->stage($d, $c, ['location' => $this->normalize($value)])),

            // Ticket seat details. Free text on all three — see the migration.
            Schema\Str::make('listingSection')
                ->writable(fn (Discussion $d, Context $c) => $this->canWriteListing($d, $c))
                ->nullable()
                ->get(fn (Discussion $d) => $d->listing?->section)
                ->set(fn (Discussion $d, ?string $value, Context $c) => $this->stage($d, $c, ['section' => $this->normalize($value)])),

            Schema\Str::make('listingRow')
                ->writable(fn (Discussion $d, Context $c) => $this->canWriteListing($d, $c))
                ->nullable()
                ->get(fn (Discussion $d) => $d->listing?->row)
                ->set(fn (Discussion $d, ?string $value, Context $c) => $this->stage($d, $c, ['row' => $this->normalize($value)])),

            Schema\Str::make('listingSeats')
                ->writable(fn (Discussion $d, Context $c) => $this->canWriteListing($d, $c))
                ->nullable()
                ->get(fn (Discussion $d) => $d->listing?->seats)
                ->set(fn (Discussion $d, ?string $value, Context $c) => $this->stage($d, $c, ['seats' => $this->normalize($value)])),

            // Which stadium chart this listing's section belongs to. Nullable
            // and never required — a seller who skips it still gets their
            // section, row and seats shown as text, as before.
            Schema\Number::make('listingSeatmapId')
                ->writable(fn (Discussion $d, Context $c) => $this->canWriteListing($d, $c))
                ->nullable()
                ->get(fn (Discussion $d) => $d->listing?->seatmap_id)
                ->set(fn (Discussion $d, $value, Context $c) => $this->stage($d, $c, ['seatmap_id' => $this->mapId($value)])),

            /*
             * The chart to draw, with the point already resolved.
             *
             * 🚨 The section table never leaves the server. A fully traced
             * ground carries a hundred-odd coordinates and the browser needs
             * exactly one of them — the one for the listing in front of it.
             *
             * 🚨 `point` is null when that section was not traced, and the
             * front end must not invent one. A star in the wrong half of a
             * ground is worse than no star: people believe a picture over a
             * sentence, and the seller's own text is at least honest.
             */
            Schema\Arr::make('listingSeatMap')
                ->nullable()
                ->get(function (Discussion $d) {
                    $listing = $d->listing;
                    $map = $listing?->seatmap_id ? $listing->seatMap : null;

                    if (! $map || blank($map->image_path)) {
                        return null;
                    }

                    return $map->toSummary() + ['point' => $listing->seatPoint()];
                }),

            // Pre-assembled so the hero and the list meta do not each build it
            // and drift — and null when there is nothing, so a template can
            // test it rather than render an empty line with a ticket icon.
            Schema\Str::make('listingSeatDisplay')
                ->nullable()
                ->get(fn (Discussion $d) => $d->listing?->seatDisplay()),

            Schema\DateTime::make('listingSoldAt')
                ->get(fn (Discussion $d) => $d->listing?->sold_at),

            Schema\DateTime::make('listingBumpedAt')
                ->get(fn (Discussion $d) => $d->listing?->bumped_at),

            Schema\Arr::make('listingImages')
                ->get(fn (Discussion $d) => $d->listing?->imageUrls() ?? []),

            Schema\Boolean::make('bumpListing')
                ->writable(fn (Discussion $d, Context $c) => $c->updating() && $c->getActor()->can('bumpListing', $d))
                ->get(fn () => false)
                ->set(function (Discussion $d, bool $value, Context $c) {
                    if ($value) {
                        $this->stage($d, $c, ['__bump' => true]);
                    }
                }),
        ];
    }

    protected function isClassifieds(Discussion $discussion): bool
    {
        if (! $discussion->relationLoaded('tags')) {
            $discussion->load('tags');
        }

        return $discussion->tags->contains(fn ($tag) => (bool) ($tag->is_classifieds ?? false));
    }

    protected function canWriteListing(Discussion $discussion, Context $context): bool
    {
        if ($context->creating()) {
            return true;
        }

        return $context->getActor()->can('editListing', $discussion);
    }

    protected function canChangeStatus(Discussion $discussion, Context $context): bool
    {
        if ($context->creating()) {
            return true;
        }

        return $context->getActor()->can('markListingSold', $discussion);
    }

    protected function normalize(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    /**
     * 🚨 Zero and the empty string both mean "no ground", not chart number 0.
     *
     * A <select> with nothing chosen posts "", and a cleared one can post 0;
     * casting either straight to int would bind the listing to an id that
     * cannot exist and leave the advert silently showing no chart with no way
     * to tell why.
     */
    protected function mapId(mixed $value): ?int
    {
        if ($value === null || $value === '' || ! is_numeric($value)) {
            return null;
        }

        return ((int) $value) > 0 ? (int) $value : null;
    }

    protected function numeric(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_numeric($value)) {
            return null;
        }

        return (float) $value;
    }

    protected static function stashMap(): WeakMap
    {
        if (static::$stash === null) {
            /** @var WeakMap<Discussion, array{stash: array, registered: bool}> $map */
            $map = new WeakMap();
            static::$stash = $map;
        }

        return static::$stash;
    }

    protected function stage(Discussion $discussion, Context $context, array $changes): void
    {
        $map = static::stashMap();
        $entry = $map[$discussion] ?? ['stash' => [], 'registered' => false];

        $entry['stash'] = array_merge($entry['stash'], $changes);

        if (! $entry['registered']) {
            $entry['registered'] = true;
            $actor = $context->getActor();

            $discussion->afterSave(function (Discussion $discussion) use ($actor) {
                $this->persist($discussion, $actor);
            });
        }

        $map[$discussion] = $entry;
    }

    protected function persist(Discussion $discussion, User $actor): void
    {
        $map = static::stashMap();
        $entry = $map[$discussion] ?? null;

        if ($entry !== null) {
            unset($map[$discussion]);
        }

        $stash = $entry['stash'] ?? null;

        if (! $stash) {
            return;
        }

        if (! $this->isClassifieds($discussion)) {
            return;
        }

        $listing = $discussion->listing()->first() ?? new Listing();
        $listing->discussion_id = $discussion->id;
        $previousStatus = $listing->exists ? $listing->status : null;
        $isNew = ! $listing->exists;

        if (! $listing->status) {
            $listing->status = Listing::STATUS_ACTIVE;
        }

        $shouldBump = ! empty($stash['__bump']);
        unset($stash['__bump']);

        foreach ($stash as $key => $value) {
            $listing->{$key} = $value;
        }

        if (! $listing->currency) {
            $listing->currency = (string) $this->settings->get('flarum-classifieds.default_currency', 'USD');
        }

        $this->validator->assertValid([
            'label' => $listing->label,
            'status' => $listing->status,
            'price' => $listing->price,
            'price_max' => $listing->price_max,
            'currency' => $listing->currency,
            'location' => $listing->location,
            'section' => $listing->section,
            'row' => $listing->row,
            'seats' => $listing->seats,
        ]);

        if (in_array($listing->status, [Listing::STATUS_SOLD, Listing::STATUS_COMPLETED], true)) {
            $listing->sold_at = $listing->sold_at ?? Carbon::now();
        } elseif ($listing->status === Listing::STATUS_ACTIVE) {
            $listing->sold_at = null;
        }

        if ($isNew || $shouldBump) {
            $listing->bumped_at = Carbon::now();
        }

        $listing->save();

        $discussion->setRelation('listing', $listing);

        if ($previousStatus !== null && $previousStatus !== $listing->status) {
            if ($listing->status === Listing::STATUS_ACTIVE) {
                $this->events->dispatch(new ListingWasReopened($discussion, $listing, $actor));
            } else {
                $this->events->dispatch(new ListingWasMarkedSold($discussion, $listing, $actor));
            }
        }

        if ($shouldBump && ! $isNew) {
            $this->events->dispatch(new ListingWasBumped($discussion, $listing, $actor));
        }
    }
}
