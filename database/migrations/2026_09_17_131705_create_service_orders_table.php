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
        Schema::create('service_orders', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('service_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('contract_version_id')->constrained()->restrictOnDelete();
            $table->char('purchase_key', 64)->unique();
            $table->string('status', 40)->default('awaiting_contract');
            $table->string('service_name_snapshot');
            $table->text('service_description_snapshot')->nullable();
            $table->unsignedBigInteger('cari_plus_product_id_snapshot');
            $table->char('currency', 3)->default('TRY');
            $table->decimal('unit_price', 12, 2);
            $table->decimal('tax_rate', 5, 2);
            $table->boolean('price_includes_tax')->default(true);
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->index(['customer_id', 'status', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('service_orders');
    }
};
