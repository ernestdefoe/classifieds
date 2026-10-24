<?php

/*
 * This file is part of ramon/classifieds.
 */

namespace Flarum\Classifieds\Console;

use Carbon\Carbon;
use Flarum\Classifieds\Listing;
use Flarum\Console\AbstractCommand;
use Flarum\Discussion\Discussion;
use Flarum\Foundation\Paths;
use Flarum\Settings\SettingsRepositoryInterface;
use Symfony\Component\Console\Input\InputOption;

class PruneListingsCommand extends AbstractCommand
{
    public function __construct(
        protected SettingsRepositoryInterface $settings,
        protected Paths $paths
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
                    // Age runs from the last bump, or from when the listing was
                    // posted if it was never bumped (it used to be skipped).
                    $q2->where(fn ($age) => $age
                        ->where('bumped_at', '<', $threshold)
                        ->orWhere(fn ($never) => $never->whereNull('bumped_at')->where('created_at', '<', $threshold)));

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

        $pruned = 0;
        $dir = $this->paths->public.'/assets/classifieds/';

        $query->select(['discussion_id', 'screenshots'])->chunkById(500, function ($listings) use (&$pruned, $dir) {
            // The discussion goes for good (its listing row cascades), so the
            // photos the extension stored for it would otherwise be orphaned.
            foreach ($listings as $listing) {
                foreach ((array) $listing->screenshots as $filename) {
                    if (is_string($filename) && $filename !== '' && basename($filename) === $filename) {
                        @unlink($dir.$filename);
                    }
                }
            }

            $pruned += Discussion::query()->whereIn('id', $listings->pluck('discussion_id'))->delete();
        }, 'discussion_id');

        $this->info("Pruned $pruned classifieds discussion(s).");

        return 0;
    }
}
