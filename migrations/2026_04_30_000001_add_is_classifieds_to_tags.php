<?php

/*
 * This file is part of ramon/classifieds.
 */

use Flarum\Database\Migration;

return Migration::addColumns('tags', [
    'is_classifieds' => ['boolean', 'default' => 0],
]);
