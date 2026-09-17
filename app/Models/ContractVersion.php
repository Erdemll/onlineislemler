<?php

namespace App\Models;

use Database\Factories\ContractVersionFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

class ContractVersion extends Model
{
    /** @use HasFactory<ContractVersionFactory> */
    use HasFactory, HasUuids;

    protected $fillable = [
        'uuid', 'contract_id', 'version', 'source_document_path',
        'source_document_hash', 'effective_at', 'published_at',
    ];

    protected function casts(): array
    {
        return [
            'effective_at' => 'datetime',
            'published_at' => 'datetime',
        ];
    }

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class, 'contract_service')->withPivot('is_required')->withTimestamps();
    }

    public function serviceOrders(): HasMany
    {
        return $this->hasMany(ServiceOrder::class);
    }

    protected static function booted(): void
    {
        static::updating(function (ContractVersion $version): void {
            if ($version->getOriginal('published_at') !== null) {
                throw new LogicException('Yayımlanmış sözleşme sürümleri değiştirilemez.');
            }
        });

        static::deleting(function (ContractVersion $version): void {
            if ($version->published_at !== null) {
                throw new LogicException('Yayımlanmış sözleşme sürümleri silinemez.');
            }
        });
    }
}
