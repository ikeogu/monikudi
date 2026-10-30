<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    protected $primaryKey = 'key';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = ['key', 'value'];

    public const DEFAULTS = [
        'business_name' => 'Grace Daily Contribution',
        'tagline' => 'Save Daily, Grow Steadily',
        'phone' => '',
        'address' => '',
        'customer_id_prefix' => 'AJ',
        'receipt_footer' => 'Thank you for saving with us!',
    ];

    /**
     * All settings, with defaults for anything not saved yet.
     *
     * @return array<string, string|null>
     */
    public static function values(): array
    {
        return Cache::rememberForever('settings', fn () => array_merge(
            self::DEFAULTS,
            static::query()->pluck('value', 'key')->all(),
        ));
    }

    public static function get(string $key): ?string
    {
        return static::values()[$key] ?? null;
    }

    /**
     * @param  array<string, string|null>  $values
     */
    public static function put(array $values): void
    {
        foreach ($values as $key => $value) {
            static::updateOrCreate(['key' => $key], ['value' => $value]);
        }

        Cache::forget('settings');
    }
}
