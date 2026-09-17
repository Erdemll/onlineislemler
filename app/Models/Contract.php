<?php

namespace App\Models;

use Database\Factories\ContractFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Contract extends Model
{
    /** @use HasFactory<ContractFactory> */
    use HasFactory, HasUuids;

    protected $fillable = ['uuid', 'name', 'slug', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function versions(): HasMany
    {
        return $this->hasMany(ContractVersion::class);
    }
}
