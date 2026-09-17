<?php

namespace App\Models;

use Database\Factories\ContractSigningChallengeFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContractSigningChallenge extends Model
{
    /** @use HasFactory<ContractSigningChallengeFactory> */
    use HasFactory, HasUuids;

    protected $fillable = [
        'uuid', 'service_order_id', 'customer_id', 'delivery_channel',
        'delivery_destination', 'code_hash', 'attempts', 'signature_path',
        'signature_hash', 'source_document_hash', 'ip_address', 'user_agent',
        'session_identifier_hash', 'expires_at', 'verified_at', 'consumed_at',
    ];

    protected $hidden = ['code_hash'];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'verified_at' => 'datetime',
            'consumed_at' => 'datetime',
        ];
    }

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function serviceOrder(): BelongsTo
    {
        return $this->belongsTo(ServiceOrder::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
