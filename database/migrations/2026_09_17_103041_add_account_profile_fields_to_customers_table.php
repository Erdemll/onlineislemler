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
        Schema::table('customers', function (Blueprint $table) {
            $table->string('account_type', 20)->default('individual')->after('cari_plus_current_account_code');
            $table->text('national_id')->nullable()->after('account_type');
            $table->char('national_id_hash', 64)->nullable()->unique()->after('national_id');
            $table->text('tax_number')->nullable()->after('national_id_hash');
            $table->char('tax_number_hash', 64)->nullable()->unique()->after('tax_number');
            $table->string('tax_office')->nullable()->after('tax_number_hash');
            $table->string('company_title')->nullable()->after('tax_office');
            $table->string('mobile_phone', 20)->nullable()->after('phone');
            $table->char('province_code', 2)->nullable()->after('mobile_phone');
            $table->string('province', 100)->nullable()->after('province_code');
            $table->string('district', 100)->nullable()->after('province');
            $table->text('address_line')->nullable()->after('district');
            $table->boolean('is_public_institution')->default(false)->after('address_line');
            $table->text('spending_unit_tax_number')->nullable()->after('is_public_institution');
            $table->string('spending_unit_title')->nullable()->after('spending_unit_tax_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn([
                'account_type',
                'national_id',
                'national_id_hash',
                'tax_number',
                'tax_number_hash',
                'tax_office',
                'company_title',
                'mobile_phone',
                'province_code',
                'province',
                'district',
                'address_line',
                'is_public_institution',
                'spending_unit_tax_number',
                'spending_unit_title',
            ]);
        });
    }
};
