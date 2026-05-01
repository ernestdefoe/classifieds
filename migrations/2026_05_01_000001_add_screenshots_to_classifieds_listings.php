<?php

/*
 * This file is part of ramon/classifieds.
 */

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;

return [
    'up' => function (Builder $schema) {
        if (! $schema->hasColumn('classifieds_listings', 'screenshots')) {
            $schema->table('classifieds_listings', function (Blueprint $table) {
                $table->json('screenshots')->nullable()->after('location');
            });
        }
    },
    'down' => function (Builder $schema) {
        if ($schema->hasColumn('classifieds_listings', 'screenshots')) {
            $schema->table('classifieds_listings', function (Blueprint $table) {
                $table->dropColumn('screenshots');
            });
        }
    },
];
