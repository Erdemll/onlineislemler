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
            $table->index(['contract_acceptance_id', 'occurred_at']);
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
