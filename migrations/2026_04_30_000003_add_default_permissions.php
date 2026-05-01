<?php

/*
 * This file is part of ramon/classifieds.
 */

use Flarum\Database\Migration;
use Flarum\Group\Group;

return Migration::addPermissions([
    'discussion.markListingSold' => Group::MEMBER_ID,
    'discussion.bumpListing' => Group::MEMBER_ID,
    'discussion.editListing' => Group::MEMBER_ID,
]);
