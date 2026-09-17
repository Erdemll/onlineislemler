<?php

namespace App\Enums;

enum SupportTicketStatus: string
{
    case AwaitingSupport = 'awaiting_support';
    case AwaitingCustomer = 'awaiting_customer';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::AwaitingSupport => 'Destek yanıtı bekleniyor',
            self::AwaitingCustomer => 'Yanıtınız bekleniyor',
            self::Closed => 'Kapatıldı',
        };
    }
}
