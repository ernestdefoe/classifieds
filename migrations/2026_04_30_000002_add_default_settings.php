<?php

/*
 * This file is part of ramon/classifieds.
 */

use Flarum\Database\Migration;

return Migration::addSettings([
    'flarum-classifieds.default_currency' => 'USD',
    'flarum-classifieds.require_label' => '1',
    'flarum-classifieds.require_price' => '1',
    'flarum-classifieds.require_location' => '0',
    'flarum-classifieds.allow_price_range' => '1',
    'flarum-classifieds.auto_prune_days' => '0',
    'flarum-classifieds.auto_prune_sold' => '0',
    'flarum-classifieds.allowed_labels' => 'iso,wtb,wts,trade',
    'flarum-classifieds.show_currency_symbol' => '1',
]);
