<?php

namespace App\Models;

use Database\Factories\PaymentCallbackFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentCallback extends Model
{
    /** @use HasFactory<PaymentCallbackFactory> */
    use HasFactory, HasUuids;

    protected $fillable = [
        'uuid',
        'payment_id',
        'order_id',
        'transaction_id',
        'code',
        'message',
        'bank_response_code',
        'bank_response_message',
        'request_status',
        'md_status',
        'hash_valid',
        'received_at',
        'payload',
    ];

    protected function casts(): array
    {
        return [
            'hash_valid' => 'boolean',
            'received_at' => 'datetime',
            'payload' => 'array',
        ];
    }

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}
