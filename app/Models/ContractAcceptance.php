<?php

namespace App\Models;

use App\Enums\ContractAcceptanceMethod;
use Database\Factories\ContractAcceptanceFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

class ContractAcceptance extends Model
{
    /** @use HasFactory<ContractAcceptanceFactory> */
    use HasFactory, HasUuids;

    protected $fillable = [
        'uuid', 'contract_version_id', 'service_order_id', 'customer_id',
        'contract_signing_challenge_id', 'contract_name_snapshot',
        'contract_version_snapshot', 'signer_name_snapshot', 'company_title_snapshot',
        'email_snapshot', 'phone_snapshot', 'acceptance_method', 'delivery_channel',
        'source_document_hash', 'signature_hash', 'signed_document_hash',
        'signature_path', 'document_path', 'accepted_at', 'ip_address',
        'user_agent', 'session_identifier_hash',
    ];

    protected function casts(): array
    {
        return [
            'acceptance_method' => ContractAcceptanceMethod::class,
            'accepted_at' => 'datetime',
        ];
    }

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function contractVersion(): BelongsTo
    {
        return $this->belongsTo(ContractVersion::class);
    }

    public function serviceOrder(): BelongsTo
    {
        return $this->belongsTo(ServiceOrder::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function signingChallenge(): BelongsTo
    {
        return $this->belongsTo(ContractSigningChallenge::class, 'contract_signing_challenge_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(ContractAcceptanceEvent::class);
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Sözleşme kabul kayıtları değiştirilemez.'));
        static::deleting(fn () => throw new LogicException('Sözleşme kabul kayıtları silinemez.'));
    }
}
