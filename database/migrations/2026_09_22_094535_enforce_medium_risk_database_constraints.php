<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $duplicateAccount = DB::table('customers')
            ->select('cari_plus_current_account_id')
            ->whereNotNull('cari_plus_current_account_id')
            ->groupBy('cari_plus_current_account_id')
            ->havingRaw('COUNT(*) > 1')
            ->first();

        if ($duplicateAccount !== null) {
            throw new RuntimeException('Mükerrer Cari Plus cari hesap eşleşmeleri düzeltilmeden migration çalıştırılamaz.');
        }

        Schema::table('customers', function (Blueprint $table): void {
            $table->dropIndex(['cari_plus_current_account_id']);
            $table->unique('cari_plus_current_account_id', 'customers_cari_plus_account_unique');
        });

        if (! in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }

        $this->assertConsistentRelationships();

        foreach ($this->mariaDbStatements() as $statement) {
            DB::unprepared($statement);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            foreach ($this->mariaDbDownStatements() as $statement) {
                DB::unprepared($statement);
            }
        }

        Schema::table('customers', function (Blueprint $table): void {
            $table->dropUnique('customers_cari_plus_account_unique');
            $table->index('cari_plus_current_account_id');
        });
    }

    private function assertConsistentRelationships(): void
    {
        $hasMismatch = DB::table('contract_signing_challenges as challenge')
            ->join('service_orders as service_order', 'service_order.id', '=', 'challenge.service_order_id')
            ->whereColumn('challenge.customer_id', '!=', 'service_order.customer_id')
            ->exists()
            || DB::table('contract_acceptances as acceptance')
                ->join('service_orders as service_order', 'service_order.id', '=', 'acceptance.service_order_id')
                ->join('contract_signing_challenges as challenge', 'challenge.id', '=', 'acceptance.contract_signing_challenge_id')
                ->where(function ($query): void {
                    $query->whereColumn('acceptance.customer_id', '!=', 'service_order.customer_id')
                        ->orWhereColumn('acceptance.contract_version_id', '!=', 'service_order.contract_version_id')
                        ->orWhereColumn('challenge.service_order_id', '!=', 'acceptance.service_order_id')
                        ->orWhereColumn('challenge.customer_id', '!=', 'acceptance.customer_id');
                })->exists()
            || DB::table('invoices as invoice')
                ->join('service_orders as service_order', 'service_order.id', '=', 'invoice.service_order_id')
                ->whereColumn('invoice.customer_id', '!=', 'service_order.customer_id')
                ->exists();

        if ($hasMismatch) {
            throw new RuntimeException('İlişkisel olarak tutarsız sözleşme veya fatura kayıtları düzeltilmeden migration çalıştırılamaz.');
        }
    }

    /** @return list<string> */
    private function mariaDbStatements(): array
    {
        return [
            'ALTER TABLE service_orders ADD UNIQUE KEY service_orders_id_customer_contract_unique (id, customer_id, contract_version_id), ADD CONSTRAINT chk_service_orders_status CHECK (BINARY status IN (\'awaiting_contract\', \'otp_pending\', \'contract_accepted\', \'invoice_failed\', \'invoiced\', \'paid\', \'service_provisioned\')), ADD CONSTRAINT chk_service_orders_financial CHECK (unit_price >= 0 AND tax_rate BETWEEN 0 AND 100)',
            'ALTER TABLE contract_signing_challenges DROP FOREIGN KEY contract_signing_challenges_service_order_id_foreign, ADD UNIQUE KEY challenges_id_order_customer_unique (id, service_order_id, customer_id), ADD KEY challenges_order_customer_index (service_order_id, customer_id), ADD CONSTRAINT challenges_order_customer_foreign FOREIGN KEY (service_order_id, customer_id) REFERENCES service_orders (id, customer_id) ON DELETE RESTRICT ON UPDATE RESTRICT, ADD CONSTRAINT chk_challenges_delivery_channel CHECK (BINARY delivery_channel IN (\'email\', \'sms\'))',
            'ALTER TABLE contract_acceptances ADD UNIQUE KEY acceptances_id_order_customer_unique (id, service_order_id, customer_id), ADD KEY acceptances_order_customer_contract_index (service_order_id, customer_id, contract_version_id), ADD KEY acceptances_challenge_order_customer_index (contract_signing_challenge_id, service_order_id, customer_id), ADD CONSTRAINT acceptances_order_customer_contract_foreign FOREIGN KEY (service_order_id, customer_id, contract_version_id) REFERENCES service_orders (id, customer_id, contract_version_id) ON DELETE RESTRICT ON UPDATE RESTRICT, ADD CONSTRAINT acceptances_challenge_order_customer_foreign FOREIGN KEY (contract_signing_challenge_id, service_order_id, customer_id) REFERENCES contract_signing_challenges (id, service_order_id, customer_id) ON DELETE RESTRICT ON UPDATE RESTRICT, ADD CONSTRAINT chk_acceptances_method_channel CHECK ((BINARY acceptance_method = \'email_otp\' AND BINARY delivery_channel = \'email\') OR (BINARY acceptance_method = \'sms_otp\' AND BINARY delivery_channel = \'sms\'))',
            'ALTER TABLE contract_acceptance_events ADD KEY events_order_customer_index (service_order_id, customer_id), ADD KEY events_acceptance_order_customer_index (contract_acceptance_id, service_order_id, customer_id), ADD CONSTRAINT events_order_customer_foreign FOREIGN KEY (service_order_id, customer_id) REFERENCES service_orders (id, customer_id) ON DELETE RESTRICT ON UPDATE RESTRICT, ADD CONSTRAINT events_acceptance_order_customer_foreign FOREIGN KEY (contract_acceptance_id, service_order_id, customer_id) REFERENCES contract_acceptances (id, service_order_id, customer_id) ON DELETE RESTRICT ON UPDATE RESTRICT, ADD CONSTRAINT chk_events_hash_version CHECK (BINARY hash_version IN (\'sha256-v1\', \'hmac-sha256-v2\'))',
            'ALTER TABLE invoices DROP FOREIGN KEY invoices_customer_id_foreign, ADD KEY invoices_order_customer_index (service_order_id, customer_id), ADD CONSTRAINT invoices_customer_restrict_foreign FOREIGN KEY (customer_id) REFERENCES customers (id) ON DELETE RESTRICT ON UPDATE RESTRICT, ADD CONSTRAINT invoices_order_customer_foreign FOREIGN KEY (service_order_id, customer_id) REFERENCES service_orders (id, customer_id) ON DELETE RESTRICT ON UPDATE RESTRICT, ADD CONSTRAINT chk_invoices_status CHECK (BINARY status IN (\'pending\', \'draft\', \'unpaid\', \'paid\', \'cancelled\', \'partial_refund\', \'refunded\', \'failed\')), ADD CONSTRAINT chk_invoices_financial CHECK (subtotal >= 0 AND tax_amount >= 0 AND total >= 0)',
            'ALTER TABLE invoice_items DROP FOREIGN KEY invoice_items_invoice_id_foreign, ADD CONSTRAINT invoice_items_invoice_restrict_foreign FOREIGN KEY (invoice_id) REFERENCES invoices (id) ON DELETE RESTRICT ON UPDATE RESTRICT, ADD CONSTRAINT chk_invoice_items_financial CHECK (quantity > 0 AND unit_price >= 0 AND tax_rate BETWEEN 0 AND 100 AND line_total >= 0)',
            'ALTER TABLE services ADD CONSTRAINT chk_services_financial CHECK (price >= 0 AND tax_rate BETWEEN 0 AND 100)',
            'ALTER TABLE customers ADD CONSTRAINT chk_customers_account_type CHECK (BINARY account_type IN (\'individual\', \'corporate\'))',
            'ALTER TABLE support_messages DROP FOREIGN KEY support_messages_support_ticket_id_foreign, ADD CONSTRAINT support_messages_ticket_restrict_foreign FOREIGN KEY (support_ticket_id) REFERENCES support_tickets (id) ON DELETE RESTRICT ON UPDATE RESTRICT, ADD CONSTRAINT chk_support_messages_sender CHECK (BINARY sender_type IN (\'customer\', \'admin\'))',
        ];
    }

    /** @return list<string> */
    private function mariaDbDownStatements(): array
    {
        return [
            'ALTER TABLE support_messages DROP FOREIGN KEY support_messages_ticket_restrict_foreign, DROP CONSTRAINT chk_support_messages_sender, ADD CONSTRAINT support_messages_support_ticket_id_foreign FOREIGN KEY (support_ticket_id) REFERENCES support_tickets (id) ON DELETE CASCADE',
            'ALTER TABLE customers DROP CONSTRAINT chk_customers_account_type',
            'ALTER TABLE services DROP CONSTRAINT chk_services_financial',
            'ALTER TABLE invoice_items DROP FOREIGN KEY invoice_items_invoice_restrict_foreign, DROP CONSTRAINT chk_invoice_items_financial, ADD CONSTRAINT invoice_items_invoice_id_foreign FOREIGN KEY (invoice_id) REFERENCES invoices (id) ON DELETE CASCADE',
            'ALTER TABLE invoices DROP FOREIGN KEY invoices_customer_restrict_foreign, DROP FOREIGN KEY invoices_order_customer_foreign, DROP INDEX invoices_order_customer_index, DROP CONSTRAINT chk_invoices_status, DROP CONSTRAINT chk_invoices_financial, ADD CONSTRAINT invoices_customer_id_foreign FOREIGN KEY (customer_id) REFERENCES customers (id) ON DELETE CASCADE',
            'ALTER TABLE contract_acceptance_events DROP FOREIGN KEY events_order_customer_foreign, DROP FOREIGN KEY events_acceptance_order_customer_foreign, DROP INDEX events_order_customer_index, DROP INDEX events_acceptance_order_customer_index, DROP CONSTRAINT chk_events_hash_version',
            'ALTER TABLE contract_acceptances DROP FOREIGN KEY acceptances_order_customer_contract_foreign, DROP FOREIGN KEY acceptances_challenge_order_customer_foreign, DROP INDEX acceptances_id_order_customer_unique, DROP INDEX acceptances_order_customer_contract_index, DROP INDEX acceptances_challenge_order_customer_index, DROP CONSTRAINT chk_acceptances_method_channel',
            'ALTER TABLE contract_signing_challenges DROP FOREIGN KEY challenges_order_customer_foreign, DROP INDEX challenges_id_order_customer_unique, DROP INDEX challenges_order_customer_index, DROP CONSTRAINT chk_challenges_delivery_channel, ADD CONSTRAINT contract_signing_challenges_service_order_id_foreign FOREIGN KEY (service_order_id) REFERENCES service_orders (id) ON DELETE CASCADE',
            'ALTER TABLE service_orders DROP INDEX service_orders_id_customer_contract_unique, DROP CONSTRAINT chk_service_orders_status, DROP CONSTRAINT chk_service_orders_financial',
        ];
    }
};
