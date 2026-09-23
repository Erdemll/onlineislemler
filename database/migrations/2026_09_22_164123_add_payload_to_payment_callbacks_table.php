<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_callbacks', function (Blueprint $table) {
            $table->text('payload')->nullable()->after('received_at');
        });
    }

    public function down(): void
    {
        Schema::table('payment_callbacks', function (Blueprint $table) {
            $table->dropColumn('payload');
        });
    }
};
