<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Services\BackupManager;
use Inertia\Inertia;

class BackupController extends Controller
{
    public function index(BackupManager $backups)
    {
        return Inertia::render('Backups/Index', [
            'backups' => $backups->all(),
            'disk' => config('backup.disk'),
            'keep' => config('backup.keep'),
            'lastBackupAt' => Setting::get('last_backup_at'),
        ]);
    }

    public function store(BackupManager $backups)
    {
        $backups->create();

        return back()->with('success', 'Backup completed.');
    }

    public function download(string $backup, BackupManager $backups)
    {
        abort_unless($backups->exists($backup), 404);

        return $backups->disk()->download($backups->path($backup));
    }
}
