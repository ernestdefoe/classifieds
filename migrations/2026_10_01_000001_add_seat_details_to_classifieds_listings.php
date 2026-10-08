<?php

use Flarum\Database\Migration;

/**
 * Section, row and seats on a listing.
 *
 * 🚨 All three are STRINGS, not integers.
 *
 * A section is "C", "114", "Upper 320" or "Club Level C"; a row is "AA" as
 * often as "12"; and seats are a list or a range — "4-7", "12, 14". Typing any
 * of them as a number loses the real value and silently stores 0 for the ones
 * that are not numeric at all.
 */
return Migration::addColumns('classifieds_listings', [
    'section' => ['string', 'length' => 32, 'nullable' => true],
    'row' => ['string', 'length' => 16, 'nullable' => true],
    'seats' => ['string', 'length' => 64, 'nullable' => true],
]);
