<?php

/*
 * This file is part of ramon/classifieds.
 */

namespace Flarum\Classifieds;

use Carbon\Carbon;
use Flarum\Database\AbstractModel;
use Illuminate\Contracts\Cache\Repository;

/**
 * A stadium seating chart and the traced position of each of its sections.
 *
 * @property int $id
 * @property string $title
 * @property string $image_path
 * @property int $image_width
 * @property int $image_height
 * @property array $sections
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class SeatMap extends AbstractModel
{
    protected $table = 'classifieds_seatmaps';

    protected $casts = [
        'image_width' => 'int',
        'image_height' => 'int',
        'sections' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $fillable = [
        'title',
        'image_path',
        'image_width',
        'image_height',
        'sections',
    ];

    /** Cache key for the list the composer offers; see offered(). */
    public const OFFERED_CACHE_KEY = 'classifieds.seatmaps.offered';

    /**
     * Any change to a chart changes what the composer may offer, so the cached
     * list goes with it.
     */
    protected static function booted(): void
    {
        $forget = fn () => resolve(Repository::class)->forget(self::OFFERED_CACHE_KEY);

        static::saved($forget);
        static::deleted($forget);
    }

    /**
     * The charts a seller may pick from — only traced ones with an image.
     *
     * 🚨 Cached. This rides on the forum payload, so it was a full read of
     * the seat-map table, every chart's section JSON included, on every page
     * load and every API request for the forum, by every visitor — to ship
     * a list that only changes when an admin traces a chart. Saving or
     * deleting a chart clears it; the TTL is only a backstop.
     *
     * @return list<array<string, mixed>>
     */
    public static function offered(): array
    {
        return resolve(Repository::class)->remember(self::OFFERED_CACHE_KEY, 86400, fn () => static::query()
            ->orderBy('title')
            ->get()
            ->filter(fn (SeatMap $m) => $m->sectionCount() > 0 && filled($m->image_path))
            ->map(fn (SeatMap $m) => $m->toSummary())
            ->values()
            ->all());
    }

    public function imageUrl(): ?string
    {
        return filled($this->image_path) ? '/assets/classifieds/'.$this->image_path : null;
    }

    public function sectionCount(): int
    {
        return is_array($this->sections) ? count($this->sections) : 0;
    }

    /**
     * Where a section sits on this chart, or null if it was never traced.
     *
     * 🚨 Returns null rather than a guess. A star in the wrong half of a ground
     * is worse than no star at all: the seller's own text is at least honest,
     * and a buyer will believe a picture over a sentence.
     *
     * @return array{x: float, y: float}|null
     */
    public function locate(?string $section): ?array
    {
        $section = trim((string) $section);

        if ($section === '' || ! is_array($this->sections)) {
            return null;
        }

        foreach ($this->sections as $name => $point) {
            if (! isset($point['x'], $point['y'])) {
                continue;
            }

            // Case-folded, so "112A" matches a section traced as "112a".
            if (strcasecmp((string) $name, $section) === 0) {
                return ['x' => (float) $point['x'], 'y' => (float) $point['y']];
            }
        }

        return null;
    }

    /**
     * What the API hands the front end for a chart that may be drawn.
     *
     * 🚨 Deliberately without the section table. A fully traced ground carries
     * a hundred-odd coordinates, and the browser never needs them — it needs
     * the one point for the listing in front of it, which the server resolves.
     */
    public function toSummary(): array
    {
        return [
            'id' => (int) $this->id,
            'title' => $this->title,
            'image' => $this->imageUrl(),
            'width' => $this->image_width,
            'height' => $this->image_height,
            'sectionCount' => $this->sectionCount(),
        ];
    }
}
