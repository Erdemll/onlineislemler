<?php

namespace App\Enums;

enum ServiceOrderStatus: string
{
    case AwaitingContract = 'awaiting_contract';
    case OtpPending = 'otp_pending';
    case ContractAccepted = 'contract_accepted';
    case InvoiceFailed = 'invoice_failed';
    case Invoiced = 'invoiced';
    case Paid = 'paid';
    case ServiceProvisioned = 'service_provisioned';
}
