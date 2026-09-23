<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_callbacks', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('payment_id')
                ->nullable()
                ->constrained('payments')
                ->restrictOnDelete();
            $table->string('order_id', 20);
            $table->string('transaction_id', 20)->nullable();
            $table->string('code', 10)->nullable();
            $table->string('message', 255)->nullable();
            $table->string('bank_response_code', 10)->nullable();
            $table->string('bank_response_message', 255)->nullable();
            $table->string('request_status', 10)->nullable();
            $table->string('md_status', 10)->nullable();
            $table->boolean('hash_valid');
            $table->timestamp('received_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_callbacks');
    }
};
