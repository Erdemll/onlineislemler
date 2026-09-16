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
            $table->unsignedBigInteger('cari_plus_product_id')
                ->nullable()
                ->after('cari_plus_service_id')
                ->unique();
            $table->string('cari_plus_sku')
                ->nullable()
                ->after('cari_plus_product_id')
                ->unique();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->dropColumn([
                'cari_plus_product_id',
                'cari_plus_sku',
            ]);
        });
    }
};
