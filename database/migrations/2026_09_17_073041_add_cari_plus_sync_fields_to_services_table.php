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
        Schema::table('services', function (Blueprint $table) {
            $table->string('currency', 3)->default('TRY')->after('price');
            $table->boolean('price_includes_tax')->default(true)->after('tax_rate');
            $table->timestampTz('cari_plus_updated_at')->nullable()->after('cari_plus_sku');
            $table->timestampTz('synced_at')->nullable()->after('cari_plus_updated_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->dropColumn([
                'currency',
                'price_includes_tax',
                'cari_plus_updated_at',
                'synced_at',
            ]);
        });
    }
};
