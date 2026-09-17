<?php

namespace App\Enums;

enum SupportMessageSender: string
{
    case Customer = 'customer';
    case Admin = 'admin';

    public function label(): string
    {
        return match ($this) {
            self::Customer => 'Müşteri',
            self::Admin => 'Destek ekibi',
        };
    }
}
