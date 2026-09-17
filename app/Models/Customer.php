<?php

namespace App\Models;

use App\Enums\CustomerType;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class Customer extends Authenticatable
{
    use HasFactory, HasUuids, Notifiable;

    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'phone',
        'mobile_phone',
        'account_type',
        'national_id',
        'tax_number',
        'tax_office',
        'company_title',
        'province_code',
        'province',
        'district',
        'address_line',
        'is_public_institution',
        'spending_unit_tax_number',
        'spending_unit_title',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'national_id',
        'national_id_hash',
        'tax_number',
        'tax_number_hash',
        'spending_unit_tax_number',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'account_type' => CustomerType::class,
            'is_public_institution' => 'boolean',
            'password_changed_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    public static function identityHash(string $value): string
    {
        return hash_hmac('sha256', $value, (string) config('app.key'));
    }

    public function billingTitle(): string
    {
        return filled($this->company_title)
            ? $this->company_title
            : trim($this->first_name.' '.$this->last_name);
    }

    protected function nationalId(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value): ?string => $value === null ? null : decrypt($value),
            set: function (?string $value): array {
                $value = filled($value) ? preg_replace('/\D+/', '', $value) : null;

                return [
                    'national_id' => $value === null ? null : encrypt($value),
                    'national_id_hash' => $value === null ? null : self::identityHash($value),
                ];
            },
        );
    }

    protected function taxNumber(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value): ?string => $value === null ? null : decrypt($value),
            set: function (?string $value): array {
                $value = filled($value) ? preg_replace('/\D+/', '', $value) : null;

                return [
                    'tax_number' => $value === null ? null : encrypt($value),
                    'tax_number_hash' => $value === null ? null : self::identityHash($value),
                ];
            },
        );
    }

    protected function spendingUnitTaxNumber(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value): ?string => $value === null ? null : decrypt($value),
            set: function (?string $value): ?string {
                $value = filled($value) ? preg_replace('/\D+/', '', $value) : null;

                return $value === null ? null : encrypt($value);
            },
        );
    }

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function serviceOrders(): HasMany
    {
        return $this->hasMany(ServiceOrder::class);
    }

    public function contractAcceptances(): HasMany
    {
        return $this->hasMany(ContractAcceptance::class);
    }
}
