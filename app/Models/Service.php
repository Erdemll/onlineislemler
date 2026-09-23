<?php

namespace App\Models;

use Database\Factories\ServiceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Service extends Model
{
    /** @use HasFactory<ServiceFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'price',
        'currency',
        'tax_rate',
        'category_id',
        'category_name',
        'price_includes_tax',
        'cari_plus_service_id',
        'cari_plus_product_id',
        'cari_plus_sku',
        'cari_plus_updated_at',
        'synced_at',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'tax_rate' => 'decimal:2',
            'category_id' => 'integer',
            'price_includes_tax' => 'boolean',
            'cari_plus_updated_at' => 'datetime',
            'synced_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function grossPrice(): float
    {
        $price = (float) $this->price;

        if ($this->price_includes_tax) {
            return $price;
        }

        return round($price * (1 + ((float) $this->tax_rate / 100)), 2);
    }

    public function grossPriceInKurus(): int
    {
        return (int) round($this->grossPrice() * 100);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function contractVersions(): BelongsToMany
    {
        return $this->belongsToMany(ContractVersion::class, 'contract_service')->withPivot('is_required')->withTimestamps();
    }

    public function serviceOrders(): HasMany
    {
        return $this->hasMany(ServiceOrder::class);
    }
}
