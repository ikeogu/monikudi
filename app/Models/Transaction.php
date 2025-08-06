<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
class Transaction extends Model
{
    use HasUlids, SoftDeletes;

    protected $fillable = [
        'customer_id',
        'date',
        'type',
        'payment_method',
        'amount',
        'description'

    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    protected static function booted()
    {
        // e.g., when a new record is created
        static::created(function ($model) {

            if($model->type == 'credit'){
                $model->customer->increment('wallet_balance', $model->amount * 100);
            }else{

                if ($model->customer->wallet_balance >= $model->amount * 100) {
                    $model->customer->decrement('wallet_balance', $model->amount * 100);
                } else {
                    // Optionally, throw or log insufficient balance
                    Log::warning("Insufficient balance for customer #{$model->customer_id}");
                }
            }
        });
    }
}