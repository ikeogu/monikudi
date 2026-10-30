<?php

namespace App\Console\Commands;

use App\Services\BackupManager;
use Illuminate\Console\Command;

use function Laravel\Prompts\select;

class BackupRestore extends Command
{
    protected $signature = 'backup:restore {name? : Backup file name, e.g. backup-2026-10-30-010000.json.gz} {--force : Skip the confirmation prompt}';

    protected $description = 'Replace all staff, customers, transactions and settings with a backup';

    public function handle(BackupManager $backups): int
    {
        $available = $backups->all()->pluck('name');

        if ($available->isEmpty()) {
            $this->error('No backups found on the ['.config('backup.disk').'] disk.');

            return self::FAILURE;
        }

        $name = $this->argument('name') ?? select('Which backup should be restored?', $available->all());

        if (! $this->option('force') && ! $this->confirm("This deletes ALL current data and replaces it with {$name}. Continue?")) {
            return self::FAILURE;
        }

        foreach ($backups->restore($name) as $table => $count) {
            $this->line("Restored {$count} {$table}.");
        }

        $this->info('Restore complete.');

        return self::SUCCESS;
    }
}
