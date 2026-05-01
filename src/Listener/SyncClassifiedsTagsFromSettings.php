<?php

/*
 * This file is part of ramon/classifieds.
 */

namespace Flarum\Classifieds\Listener;

use Flarum\Settings\Event\Saved;
use Flarum\Settings\SettingsRepositoryInterface;
use Illuminate\Database\ConnectionInterface;

class SyncClassifiedsTagsFromSettings
{
    public function __construct(
        protected SettingsRepositoryInterface $settings,
        protected ConnectionInterface $db,
    ) {
    }

    public function handle(Saved $event): void
    {
        if (! array_key_exists('flarum-classifieds.classifieds_tag_ids', $event->settings)) {
            return;
        }

        $this->sync();
    }

    public function sync(): void
    {
        $raw = (string) $this->settings->get('flarum-classifieds.classifieds_tag_ids', '[]');
        $ids = json_decode($raw, true) ?: [];
        $ids = array_values(array_unique(array_map('intval', $ids)));

        $this->db->table('tags')->update(['is_classifieds' => 0]);

        if (! empty($ids)) {
            $this->db->table('tags')
                ->whereIn('id', $ids)
                ->update(['is_classifieds' => 1]);
        }
    }
}
