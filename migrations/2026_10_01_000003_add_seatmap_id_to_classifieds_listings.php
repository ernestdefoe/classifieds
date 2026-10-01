<?php

/*
 * This file is part of ramon/classifieds.
 */

use Flarum\Database\Migration;

/**
 * Which stadium chart a ticket listing belongs to.
 *
 * 🚨 Nullable, and nothing requires it. A seller who does not pick a ground
 * still gets their section, row and seats shown as text — which is what the
 * listing did before any of this existed.
 */
return Migration::addColumns('classifieds_listings', [
    'seatmap_id' => ['integer', 'unsigned' => true, 'nullable' => true],
]);
