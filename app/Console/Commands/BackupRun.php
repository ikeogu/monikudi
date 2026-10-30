<?php

namespace App\Console\Commands;

use App\Services\BackupManager;
use Illuminate\Console\Command;

class BackupRun extends Command
{
    protected $signature = 'backup:run';

    protected $description = 'Back up staff, customers, transactions and settings to the backup disk';

    public function handle(BackupManager $backups): int
    {
        $name = $backups->create();

        $this->info("Backup saved as {$name} on the [".config('backup.disk').'] disk.');

        return self::SUCCESS;
    }
}
