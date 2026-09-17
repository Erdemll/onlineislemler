<?php

namespace App\Enums;

enum SupportTicketCategory: string
{
    case Technical = 'technical';
    case Billing = 'billing';
    case Subscription = 'subscription';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Technical => 'Teknik destek',
            self::Billing => 'Fatura ve ödeme',
            self::Subscription => 'Hizmet ve abonelik',
            self::Other => 'Diğer',
        };
    }
}
