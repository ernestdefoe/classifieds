<?php

/*
 * This file is part of ramon/classifieds.
 */

namespace Flarum\Classifieds\Api;

use Flarum\Api\Schema;

class UserResourceFields
{
    public function __invoke(): array
    {
        return [
            /*
             * 🚨 A relation aggregate, not a getter that counts.
             *
             * Every serialized user carries this field — post authors, the
             * last poster of each discussion, everyone on a user list — and a
             * getter ran one COUNT per user: 20 extra queries on a 20-member
             * page. countRelation() goes through core's EloquentBuffer, which
             * counts every user in the response in one grouped query whatever
             * include path they arrived by.
             *
             * The relation (User::classifiedsListings, extend.php) carries the
             * "visible classifieds discussion" constraints itself.
             */
            Schema\Integer::make('classifiedsListingsCount')
                ->countRelation('classifiedsListings'),
        ];
    }
}
