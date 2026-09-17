<?php

namespace App\Models;

use App\Enums\ServiceOrderStatus;
use Database\Factories\ServiceOrderFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ServiceOrder extends Model
{
    /** @use HasFactory<ServiceOrderFactory> */
    use HasFactory, HasUuids;

    protected $fillable = [
        'uuid', 'customer_id', 'service_id', 'contract_version_id', 'purchase_key',
        'status', 'service_name_snapshot', 'service_description_snapshot',
        'cari_plus_product_id_snapshot', 'currency', 'unit_price', 'tax_rate',
        'price_includes_tax', 'last_error',
    ];

    protected function casts(): array
    {
        return [
            'status' => ServiceOrderStatus::class,
            'unit_price' => 'decimal:2',
            'tax_rate' => 'decimal:2',
            'price_includes_tax' => 'boolean',
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

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function contractVersion(): BelongsTo
    {
        return $this->belongsTo(ContractVersion::class);
    }

    public function signingChallenges(): HasMany
    {
        return $this->hasMany(ContractSigningChallenge::class);
    }

    public function acceptance(): HasOne
    {
        return $this->hasOne(ContractAcceptance::class);
    }

    public function invoice(): HasOne
    {
        return $this->hasOne(Invoice::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(ContractAcceptanceEvent::class);
    }
}
