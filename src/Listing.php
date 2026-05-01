<?php

/*
 * This file is part of ramon/classifieds.
 */

namespace Flarum\Classifieds;

use Carbon\Carbon;
use Flarum\Database\AbstractModel;
use Flarum\Discussion\Discussion;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $discussion_id
 * @property string|null $label
 * @property string $status
 * @property float|null $price
 * @property float|null $price_max
 * @property string|null $currency
 * @property string|null $location
 * @property Carbon|null $sold_at
 * @property Carbon|null $bumped_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Discussion $discussion
 */
class Listing extends AbstractModel
{
    public const STATUS_ACTIVE = 'active';
    public const STATUS_SOLD = 'sold';
    public const STATUS_COMPLETED = 'completed';

    public const LABEL_ISO = 'iso';
    public const LABEL_WTB = 'wtb';
    public const LABEL_WTS = 'wts';
    public const LABEL_TRADE = 'trade';

    public const STATUSES = [self::STATUS_ACTIVE, self::STATUS_SOLD, self::STATUS_COMPLETED];
    public const LABELS = [self::LABEL_ISO, self::LABEL_WTB, self::LABEL_WTS, self::LABEL_TRADE];

    protected $table = 'classifieds_listings';

    protected $primaryKey = 'discussion_id';

    public $incrementing = false;

    protected $keyType = 'int';

    protected $casts = [
        'discussion_id' => 'int',
        'price' => 'float',
        'price_max' => 'float',
        'sold_at' => 'datetime',
        'bumped_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'screenshots' => 'array',
    ];

    protected $fillable = [
        'label',
        'status',
        'price',
        'price_max',
        'currency',
        'location',
        'sold_at',
        'bumped_at',
        'screenshots',
    ];

    public function imageUrls(): array
    {
        $screenshots = is_array($this->screenshots) ? $this->screenshots : [];

        return array_values(array_map(
            fn (string $filename) => '/assets/classifieds/'.$filename,
            $screenshots
        ));
    }

    public function discussion(): BelongsTo
    {
        return $this->belongsTo(Discussion::class, 'discussion_id');
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isSold(): bool
    {
        return $this->status === self::STATUS_SOLD;
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function markSold(): static
    {
        $this->status = self::STATUS_SOLD;
        $this->sold_at = Carbon::now();

        return $this;
    }

    public function markCompleted(): static
    {
        $this->status = self::STATUS_COMPLETED;
        $this->sold_at = $this->sold_at ?? Carbon::now();

        return $this;
    }

    public function reopen(): static
    {
        $this->status = self::STATUS_ACTIVE;
        $this->sold_at = null;

        return $this;
    }

    public function bump(): static
    {
        $this->bumped_at = Carbon::now();

        return $this;
    }
}
