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
        Schema::create('contract_acceptances', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('contract_version_id')->constrained()->restrictOnDelete();
            $table->foreignId('service_order_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('contract_signing_challenge_id')->unique()->constrained()->restrictOnDelete();
            $table->string('contract_name_snapshot');
            $table->string('contract_version_snapshot', 30);
            $table->string('signer_name_snapshot');
            $table->string('company_title_snapshot')->nullable();
            $table->string('email_snapshot');
            $table->string('phone_snapshot', 20);
            $table->string('acceptance_method', 30);
            $table->string('delivery_channel', 20);
            $table->char('source_document_hash', 64);
            $table->char('signature_hash', 64);
            $table->char('signed_document_hash', 64);
            $table->string('signature_path');
            $table->string('document_path');
            $table->timestamp('accepted_at', 6);
            $table->string('ip_address', 45);
            $table->text('user_agent')->nullable();
            $table->char('session_identifier_hash', 64);
            $table->timestamps();

            $table->index(['customer_id', 'accepted_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contract_acceptances');
    }
};
