<?php

namespace Database\Factories;

use App\Enums\ContractAcceptanceMethod;
use App\Models\ContractAcceptance;
use App\Models\ContractSigningChallenge;
use App\Models\ContractVersion;
use App\Models\Customer;
use App\Models\ServiceOrder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ContractAcceptance>
 */
class ContractAcceptanceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'contract_version_id' => ContractVersion::factory(),
            'service_order_id' => ServiceOrder::factory(),
            'customer_id' => Customer::factory()->ready(),
            'contract_signing_challenge_id' => ContractSigningChallenge::factory(),
            'contract_name_snapshot' => 'Kamera Sistemleri Abonelik Sözleşmesi',
            'contract_version_snapshot' => '1.0',
            'signer_name_snapshot' => fake()->name(),
            'company_title_snapshot' => null,
            'email_snapshot' => fake()->safeEmail(),
            'phone_snapshot' => '+905551112233',
            'acceptance_method' => ContractAcceptanceMethod::EmailOtp,
            'delivery_channel' => 'email',
            'source_document_hash' => str_repeat('a', 64),
            'signature_hash' => str_repeat('b', 64),
            'signed_document_hash' => str_repeat('c', 64),
            'signature_path' => 'contracts/signatures/test.enc',
            'document_path' => 'contracts/acceptances/test.pdf',
            'accepted_at' => now(),
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Pest',
            'session_identifier_hash' => str_repeat('d', 64),
        ];
    }
}
