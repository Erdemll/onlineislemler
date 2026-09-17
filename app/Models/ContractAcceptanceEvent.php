<?php

namespace App\Models;

use Database\Factories\ContractAcceptanceEventFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class ContractAcceptanceEvent extends Model
{
    /** @use HasFactory<ContractAcceptanceEventFactory> */
    use HasFactory;

    protected $fillable = [
        'service_order_id', 'contract_acceptance_id', 'customer_id', 'sequence',
        'event_type', 'metadata', 'previous_hash', 'event_hash', 'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'occurred_at' => 'datetime',
        ];
    }

    public function serviceOrder(): BelongsTo
    {
        return $this->belongsTo(ServiceOrder::class);
    }

    public function acceptance(): BelongsTo
    {
        return $this->belongsTo(ContractAcceptance::class, 'contract_acceptance_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Sözleşme audit kayıtları değiştirilemez.'));
        static::deleting(fn () => throw new LogicException('Sözleşme audit kayıtları silinemez.'));
    }
}
