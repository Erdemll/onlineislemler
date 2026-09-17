<?php

namespace App\Models;

use App\Enums\SupportMessageSender;
use Database\Factories\SupportMessageFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupportMessage extends Model
{
    /** @use HasFactory<SupportMessageFactory> */
    use HasFactory, HasUuids;

    protected $fillable = [
        'uuid', 'support_ticket_id', 'sender_type', 'customer_id', 'user_id',
        'sender_name_snapshot', 'body', 'ip_address', 'user_agent',
    ];

    protected function casts(): array
    {
        return ['sender_type' => SupportMessageSender::class];
    }

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(SupportTicket::class, 'support_ticket_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
