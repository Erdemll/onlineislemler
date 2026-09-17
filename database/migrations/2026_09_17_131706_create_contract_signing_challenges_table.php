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
        Schema::create('contract_signing_challenges', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('service_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->string('delivery_channel', 20);
            $table->string('delivery_destination');
            $table->string('code_hash');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->string('signature_path');
            $table->char('signature_hash', 64);
            $table->char('source_document_hash', 64);
            $table->string('ip_address', 45);
            $table->text('user_agent')->nullable();
            $table->char('session_identifier_hash', 64);
            $table->timestamp('expires_at');
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('consumed_at')->nullable();
            $table->timestamps();

            $table->index(['service_order_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contract_signing_challenges');
    }
};
