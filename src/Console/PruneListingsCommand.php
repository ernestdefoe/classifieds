<?php

/*
 * This file is part of ramon/classifieds.
 */

namespace Flarum\Classifieds\Console;

use Carbon\Carbon;
use Flarum\Classifieds\Listing;
use Flarum\Console\AbstractCommand;
use Flarum\Discussion\Discussion;
use Flarum\Settings\SettingsRepositoryInterface;
use Symfony\Component\Console\Input\InputOption;

class PruneListingsCommand extends AbstractCommand
{
    public function __construct(
        protected SettingsRepositoryInterface $settings
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('classifieds:prune')
            ->setDescription('Prune sold/inactive classifieds listings according to admin settings.')
            ->addOption('days', null, InputOption::VALUE_OPTIONAL, 'Override the configured retention window in days.')
            ->addOption('include-active', null, InputOption::VALUE_NONE, 'Also prune listings still active beyond the retention window.')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show what would happen without deleting.');
    }

    protected function fire(): int
    {
        $days = (int) ($this->input->getOption('days') ?? $this->settings->get('flarum-classifieds.auto_prune_days', 0));
        $pruneSold = (bool) $this->settings->get('flarum-classifieds.auto_prune_sold');
        $includeActive = (bool) $this->input->getOption('include-active');
        $dryRun = (bool) $this->input->getOption('dry-run');

        if ($days <= 0 && ! $pruneSold) {
            $this->info('Auto-pruning is disabled. Configure flarum-classifieds.auto_prune_days or auto_prune_sold.');

            return 0;
        }

        $threshold = $days > 0 ? Carbon::now()->subDays($days) : null;

        $query = Listing::query()->where(function ($q) use ($pruneSold, $threshold, $includeActive) {
            if ($pruneSold) {
                $q->orWhereIn('status', [Listing::STATUS_SOLD, Listing::STATUS_COMPLETED]);
            }

            if ($threshold) {
                $q->orWhere(function ($q2) use ($threshold, $includeActive) {
                    $q2->whereNotNull('bumped_at')->where('bumped_at', '<', $threshold);

                    if (! $includeActive) {
                        $q2->whereIn('status', [Listing::STATUS_SOLD, Listing::STATUS_COMPLETED]);
                    }
                });
            }
        });

        $count = $query->count();

        if ($count === 0) {
            $this->info('No listings to prune.');

            return 0;
        }

        $this->info("Found $count listing(s) to prune.");

        if ($dryRun) {
            $this->info('Dry-run: nothing was deleted.');

            return 0;
        }

        $discussionIds = $query->pluck('discussion_id')->all();

        Discussion::query()->whereIn('id', $discussionIds)->delete();

        $this->info('Pruned '.count($discussionIds).' classifieds discussion(s).');

        return 0;
    }
}
