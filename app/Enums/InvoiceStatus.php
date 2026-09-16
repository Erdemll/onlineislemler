<?php

namespace App\Enums;

enum InvoiceStatus: string
{
    case Pending = 'pending';
    case Draft = 'draft';
    case Unpaid = 'unpaid';
    case Paid = 'paid';
    case Cancelled = 'cancelled';
    case PartialRefund = 'partial_refund';
    case Refunded = 'refunded';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Aktarılıyor',
            self::Draft => 'Taslak',
            self::Unpaid => 'Ödenmedi',
            self::Paid => 'Ödendi',
            self::Cancelled => 'İptal edildi',
            self::PartialRefund => 'Kısmi iade',
            self::Refunded => 'İade edildi',
            self::Failed => 'Aktarım hatası',
        };
    }
}
