<?php

use App\Models\Customer;
use App\Models\Setting;
use App\Models\User;
use App\Services\BackupManager;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    config(['backup.disk' => 'local', 'backup.keep' => 2]);
});

test('backup:run stores a snapshot and records the time', function () {
    Customer::factory()->count(3)->create();

    $this->artisan('backup:run')->assertSuccessful();

    $backups = app(BackupManager::class)->all();
    expect($backups)->toHaveCount(1)
        ->and(Setting::get('last_backup_at'))->not->toBeNull();

    $snapshot = json_decode(gzdecode(Storage::disk('local')->get('backups/'.$backups[0]['name'])), true);
    expect($snapshot['tables']['customers'])->toHaveCount(3);
});

test('only the newest backups are kept', function () {
    foreach (range(1, 3) as $i) {
        $this->travel(1)->minutes();
        app(BackupManager::class)->create();
    }

    expect(app(BackupManager::class)->all())->toHaveCount(2);
});

test('a backup can be restored', function () {
    $customer = Customer::factory()->withBalance(1000)->create();
    $name = app(BackupManager::class)->create();

    $customer->forceDelete();
    Customer::factory()->create();

    $this->artisan("backup:restore {$name} --force")->assertSuccessful();

    expect(Customer::pluck('id')->all())->toBe([$customer->id])
        ->and(Customer::sole()->balance)->toEqual(1000);
});

test('the daily backup only runs once a day', function () {
    $backups = app(BackupManager::class);

    expect($backups->createIfDue())->not->toBeNull();
    $this->travel(2)->hours();
    expect($backups->createIfDue())->toBeNull();
    $this->travel(1)->days();
    expect($backups->createIfDue())->not->toBeNull();
});

test('admins can run and download backups', function () {
    $admin = User::factory()->create();

    $this->actingAs($admin)->post(route('backups.store'))->assertSessionHas('success');
    $name = app(BackupManager::class)->all()[0]['name'];

    $this->get(route('backups.download', $name))->assertDownload($name);
    $this->get(route('backups.download', '..%2F.env'))->assertNotFound();
});
