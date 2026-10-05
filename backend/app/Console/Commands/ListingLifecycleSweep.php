<?php

namespace App\Console\Commands;

use App\Services\ListingLifecycleService;
use Illuminate\Console\Command;

class ListingLifecycleSweep extends Command
{
    protected $signature = 'listings:lifecycle {--dry-run : Preview without sending messages or changing listings}';

    protected $description = 'Expire approved listings, warn owners, and delete unrenewed expired listings';

    public function handle(ListingLifecycleService $service): int
    {
        $counts = $service->sweep((bool) $this->option('dry-run'));
        $this->info(($this->option('dry-run') ? 'Dry run: ' : '').json_encode($counts));

        return $counts['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
