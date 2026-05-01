<?php

/*
 * This file is part of ramon/classifieds.
 */

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;

return [
    'up' => function (Builder $schema) {
        $schema->create('classifieds_listings', function (Blueprint $table) {
            $table->unsignedInteger('discussion_id')->primary();
            $table->string('label', 16)->nullable();
            $table->string('status', 16)->default('active');
            $table->decimal('price', 12, 2)->nullable();
            $table->decimal('price_max', 12, 2)->nullable();
            $table->string('currency', 8)->nullable();
            $table->string('location', 255)->nullable();
            $table->dateTime('sold_at')->nullable();
            $table->dateTime('bumped_at')->nullable();
            $table->timestamps();

            $table->index('label');
            $table->index('status');

            $table->foreign('discussion_id')
                ->references('id')->on('discussions')
                ->onDelete('cascade');
        });
    },

    'down' => function (Builder $schema) {
        $schema->drop('classifieds_listings');
    },
];
