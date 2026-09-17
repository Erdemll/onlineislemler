<?php

namespace App\Enums;

enum ContractAcceptanceMethod: string
{
    case EmailOtp = 'email_otp';
    case SmsOtp = 'sms_otp';
}
