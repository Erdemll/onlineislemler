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
        Schema::create('contract_versions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('contract_id')->constrained()->restrictOnDelete();
            $table->string('version', 30);
            $table->string('source_document_path');
            $table->char('source_document_hash', 64);
            $table->timestamp('effective_at');
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->unique(['contract_id', 'version']);
            $table->index(['contract_id', 'effective_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contract_versions');
    }
};
