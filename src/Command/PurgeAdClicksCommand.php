<?php

namespace Ralkage\AdManagement\Command;

use Carbon\Carbon;
use Flarum\Console\AbstractCommand;
use Ralkage\AdManagement\Model\AdClick;
use Symfony\Component\Console\Input\InputArgument;

class PurgeAdClicksCommand extends AbstractCommand
{
    protected function configure()
    {
        $this
            ->setName('ad-management:purge-clicks')
            ->setDescription('GDPR: Delete old ad click records to comply with data retention policies.')
            ->addArgument(
                'days',
                InputArgument::OPTIONAL,
                'Delete records older than this many days (default: 90)',
                90
            );
    }

    protected function fire(): int
    {
        $days = (int) $this->argument('days');

        if ($days < 1) {
            $this->error('Days must be at least 1.');
            return 1;
        }

        $cutoff = Carbon::now()->subDays($days);

        $deleted = AdClick::where('created_at', '<', $cutoff)->delete();

        $this->info("Deleted {$deleted} ad click record(s) older than {$days} days.");

        return 0;
    }
}
