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

    /** Whether the model-event fallback is registered in this process. */
    protected static bool $hooked = false;

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

        return $discussion->getAttribute('tags')->contains(fn ($tag) => (bool) ($tag->is_classifieds ?? false));
    }

    /**
     * Whether this is a classifieds discussion, read from the database.
     *
     * Used where a wrong answer loses data, unlike the serializer's getter,
     * where a cached relation is both correct and cheap.
     */
    /**
     * True only when this discussion is known to carry tags, none of which is a
     * classifieds tag.
     *
     * 🚨 Not the negation of isClassifieds(). A discussion whose tags are not
     * attached yet answers FALSE here and TRUE there, and that difference is the
     * whole bug this exists to avoid.
     */
    protected function isDefinitelyNotClassifieds(Discussion $discussion): bool
    {
        if (! $discussion->exists) {
            return false;
        }

        $discussion->unsetRelation('tags');
        $tags = $discussion->getAttribute('tags');

        if ($tags->isEmpty()) {
            return false;
        }

        return ! $tags->contains(fn ($tag) => (bool) ($tag->is_classifieds ?? false));
    }

    protected function isClassifiedsFresh(Discussion $discussion): bool
    {
        if ($discussion->exists) {
            $discussion->unsetRelation('tags');
        }

        return $this->isClassifieds($discussion);
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

        $entry['actor'] = $entry['actor'] ?? $context->getActor();

        if (! $entry['registered']) {
            $entry['registered'] = true;
            $actor = $entry['actor'];

            $discussion->afterSave(function (Discussion $discussion) use ($actor) {
                $this->persist($discussion, $actor);
            });

            /*
             * 🚨 And a model-event fallback, because afterSave does not fire
             * everywhere.
             *
             * On fbsfb an advert posted through the composer produced a
             * correctly tagged discussion with no price, label or seat details
             * and no error anywhere. Instrumenting the live request showed all
             * six fields staged, afterSave REGISTERED on object 1609 — and
             * Eloquent's own `saved` firing on that very same object while the
             * afterSave callback never ran. Which of the sixty-odd installed
             * extensions swallows it does not matter: a data path that silently
             * depends on one is the bug.
             *
             * Eloquent's event was measured firing on the right instance, so
             * that is what this hangs on. Both paths call persist(), and
             * persist() consumes the stash only once, so a double fire is a
             * no-op rather than a duplicate listing.
             */
            static::hookModelEvent();
        }

        $map[$discussion] = $entry;
    }

    /**
     * Flush any staged listing when a discussion is saved.
     *
     * Registered once per process. The handler looks the stash up by model
     * instance, so a discussion nothing staged costs one lookup.
     */
    protected static function hookModelEvent(): void
    {
        if (static::$hooked) {
            return;
        }

        static::$hooked = true;

        Discussion::saved(function (Discussion $discussion) {
            $entry = static::stashMap()[$discussion] ?? null;

            if ($entry === null || empty($entry['stash']) || empty($entry['actor'])) {
                return;
            }

            resolve(static::class)->persist($discussion, $entry['actor']);
        });
    }

    protected function persist(Discussion $discussion, User $actor): void
    {
        $map = static::stashMap();
        $entry = $map[$discussion] ?? null;
        $stash = $entry['stash'] ?? null;

        if (! $stash) {
            return;
        }

        /*
         * 🚨 Re-read the tags here. Do NOT trust the loaded relation.
         *
         * This guard decides whether a staged listing is written at all, and on
         * creation the `tags` relation can already be loaded and EMPTY — read
         * for serialization before flarum-tags attaches anything. The guard
         * then says "not a classifieds discussion" and the listing is dropped
         * without a word: the discussion appears, correctly tagged, carrying no
         * price, label or seat details, and nothing anywhere reports an error.
         *
         * It is environment-dependent, which is what makes it dangerous — it
         * did not reproduce on the demo and did on fbsfb, where more extensions
         * touch the same save. One extra query on save is the whole cost.
         */
        /*
         * 🚨 Skip only on positive DISPROOF — never on absent evidence.
         *
         * This guard used to demand proof that the discussion is a classifieds
         * one, and at creation that proof cannot exist yet: flarum-tags attaches
         * the tags after every save of the discussion, so instrumenting a live
         * request showed persist() entered three times, each one finding no tags
         * and returning, and the advert posted with no price, label or seat
         * details and no error anywhere.
         *
         * Nothing is protected by requiring proof here. These fields are only
         * writable through this resource, so a client that sends them is saying
         * what it wants; a row written for a discussion that turns out not to be
         * classifieds is inert, because every reader checks the tag. Losing the
         * seller's data is the expensive mistake, not keeping a spare row.
         */
        if ($this->isDefinitelyNotClassifieds($discussion)) {
            return;
        }

        unset($map[$discussion]);

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
