<?php

namespace App\Services;

use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Database-agnostic backups: each table is exported to gzipped JSON on the configured disk,
 * so no mysqldump/pg_dump binary is needed on the server.
 */
class BackupManager
{
    // Ordered so parents are restored before the rows that reference them.
    public const TABLES = ['users', 'settings', 'customers', 'transactions'];

    public function disk(): Filesystem
    {
        return Storage::disk(config('backup.disk'));
    }

    public function path(string $name): string
    {
        return trim(config('backup.path'), '/').'/'.$name;
    }

    public function exists(string $name): bool
    {
        return preg_match('/^backup-[\d-]+\.json\.gz$/', $name) === 1 && $this->disk()->exists($this->path($name));
    }

    public function create(): string
    {
        $snapshot = [
            'app' => config('app.name'),
            'created_at' => now()->toIso8601String(),
            'tables' => [],
        ];

        foreach (self::TABLES as $table) {
            $snapshot['tables'][$table] = DB::table($table)->get()->map(fn ($row) => (array) $row)->all();
        }

        $name = 'backup-'.now()->format('Y-m-d-His').'.json.gz';
        $this->disk()->put($this->path($name), gzencode(json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), 9));

        Setting::put(['last_backup_at' => now()->toIso8601String()]);
        $this->prune();

        return $name;
    }

    /**
     * Take a backup if the last one is more than a day old.
     */
    public function createIfDue(): ?string
    {
        $last = Setting::get('last_backup_at');

        if ($last && Carbon::parse($last)->gt(now()->subDay())) {
            return null;
        }

        return Cache::lock('backup:create', 600)->get(fn () => $this->create()) ?: null;
    }

    /**
     * @return Collection<int, array{name: string, size: int, created_at: string}>
     */
    public function all(): Collection
    {
        return collect($this->disk()->files(config('backup.path')))
            ->map(fn ($path) => basename($path))
            ->filter(fn ($name) => str_starts_with($name, 'backup-') && str_ends_with($name, '.json.gz'))
            ->sortDesc()
            ->values()
            ->map(fn ($name) => [
                'name' => $name,
                'size' => $this->disk()->size($this->path($name)),
                'created_at' => Carbon::createFromFormat('Y-m-d-His', substr($name, 7, 17))->toIso8601String(),
            ]);
    }

    public function prune(): void
    {
        $this->all()->slice(config('backup.keep'))->each(fn ($backup) => $this->disk()->delete($this->path($backup['name'])));
    }

    /**
     * Replace the current data with a backup. Returns the number of rows restored per table.
     *
     * @return array<string, int>
     */
    public function restore(string $name): array
    {
        if (! $this->exists($name)) {
            throw new RuntimeException("Backup {$name} was not found.");
        }

        $snapshot = json_decode(gzdecode($this->disk()->get($this->path($name))), true, flags: JSON_THROW_ON_ERROR);
        $counts = [];

        DB::transaction(function () use ($snapshot, &$counts) {
            foreach (array_reverse(self::TABLES) as $table) {
                DB::table($table)->delete();
            }

            foreach (self::TABLES as $table) {
                $rows = $snapshot['tables'][$table] ?? [];
                foreach (array_chunk($rows, 500) as $chunk) {
                    DB::table($table)->insert($chunk);
                }
                $counts[$table] = count($rows);
            }
        });

        Cache::forget('settings');

        return $counts;
    }
}
