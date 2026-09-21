<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('contract_acceptance_events')) {
            foreach (['id', 'service_order_id', 'contract_acceptance_id', 'customer_id', 'sequence', 'event_type', 'metadata', 'previous_hash', 'event_hash', 'occurred_at', 'created_at', 'updated_at'] as $column) {
                if (! Schema::hasColumn('contract_acceptance_events', $column)) {
                    throw new RuntimeException("Eksik sütun nedeniyle sözleşme olayları tablosu otomatik onarılamadı: {$column}");
                }
            }

            if (! Schema::hasIndex('contract_acceptance_events', ['service_order_id', 'sequence'], 'unique')) {
                throw new RuntimeException('Sözleşme olayları tablosundaki benzersiz sıra indeksi eksik; otomatik onarım durduruldu.');
            }

            if (! Schema::hasIndex('contract_acceptance_events', ['contract_acceptance_id', 'occurred_at'])) {
                Schema::table('contract_acceptance_events', function (Blueprint $table) {
                    $table->index(['contract_acceptance_id', 'occurred_at'], 'ca_events_acceptance_occurred_idx');
                });
            }

            return;
        }

        Schema::create('contract_acceptance_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_order_id')->constrained()->restrictOnDelete();
            $table->foreignId('contract_acceptance_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('sequence');
            $table->string('event_type', 60);
            $table->json('metadata')->nullable();
            $table->char('previous_hash', 64)->nullable();
            $table->char('event_hash', 64);
            $table->timestamp('occurred_at', 6);
            $table->timestamps();

            $table->unique(['service_order_id', 'sequence']);
            $table->index(['contract_acceptance_id', 'occurred_at'], 'ca_events_acceptance_occurred_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contract_acceptance_events');
    }
};
