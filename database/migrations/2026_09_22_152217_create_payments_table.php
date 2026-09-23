<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('customer_id')
                ->constrained('customers')
                ->restrictOnDelete();
            $table->foreignId('invoice_id')
                ->constrained('invoices')
                ->restrictOnDelete();
            $table->string('order_id', 20)->unique();
            $table->string('three_d_session_id', 256)->nullable();
            $table->string('transaction_id', 20)->nullable();
            $table->unsignedBigInteger('amount_kurus');
            $table->string('currency', 3)->default('TRY');
            $table->string('status', 20)->default('pending');
            $table->unsignedSmallInteger('installment_count')->default(0);
            $table->string('response_code', 20)->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->unsignedBigInteger('cari_plus_collection_id')->nullable();
            $table->timestamp('cari_plus_collection_synced_at')->nullable();
            $table->timestamps();
        });

        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::unprepared(
                'ALTER TABLE payments ADD CONSTRAINT chk_payments_status CHECK (BINARY status IN (\'pending\', \'paid\', \'failed\', \'cancelled\', \'refunded\'))'
                .', ADD CONSTRAINT chk_payments_financial CHECK (amount_kurus >= 0)'
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
