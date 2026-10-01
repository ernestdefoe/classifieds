<?php

/*
 * This file is part of ramon/classifieds.
 */

use Flarum\Database\Migration;
use Illuminate\Database\Schema\Blueprint;

/**
 * Stadium seating charts, plus where each section sits on one.
 *
 * 🚨 The geometry is stored here because it exists nowhere else.
 *
 * Putting a star on a seating chart needs, per venue, the position of every
 * section on one specific image. That is not published in any usable form:
 * the charts online are pictures, and the interactive ones on ticket sites are
 * proprietary. Section numbering is not standardised either, so a generic bowl
 * diagram would put the star on the wrong side of the ground often enough to
 * mislead a buyer. So it is traced once, by hand, and kept.
 */
return Migration::createTable('classifieds_seatmaps', function (Blueprint $table) {
    $table->increments('id');
    $table->string('title', 100);
    $table->string('image_path', 255)->default('');
    $table->unsignedInteger('image_width')->default(0);
    $table->unsignedInteger('image_height')->default(0);

    /*
     * { "112": {"x": 0.41, "y": 0.78}, ... }
     *
     * 🚨 Coordinates are NORMALISED (0..1), never pixels. A chart traced at
     * 1400px would otherwise put its stars in the wrong place the moment it is
     * drawn at 700px on a phone, or replaced by a better scan of the same
     * ground. Fractions of the image survive both.
     *
     * Section keys are kept exactly as the venue prints them, because that is
     * what a seller will type. Matching is case-folded at lookup time rather
     * than normalised on the way in, so the chart still shows the section the
     * way the stadium writes it.
     */
    $table->text('sections')->nullable();

    $table->timestamps();
});
