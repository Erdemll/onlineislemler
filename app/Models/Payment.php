<?php

namespace App\Models;

use App\Enums\PaymentStatus;
use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    /** @use HasFactory<PaymentFactory> */
    use HasFactory, HasUuids;

    protected $fillable = [
        'uuid',
        'customer_id',
        'invoice_id',
        'order_id',
        'three_d_session_id',
        'transaction_id',
        'amount_kurus',
        'currency',
        'status',
        'installment_count',
        'response_code',
        'paid_at',
        'cari_plus_collection_id',
        'cari_plus_collection_synced_at',
    ];

    protected function casts(): array
    {
        return [
            'amount_kurus' => 'integer',
            'installment_count' => 'integer',
            'status' => PaymentStatus::class,
            'paid_at' => 'datetime',
            'cari_plus_collection_synced_at' => 'datetime',
        ];
    }

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
