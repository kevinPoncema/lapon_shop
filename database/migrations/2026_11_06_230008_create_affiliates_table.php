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
        Schema::create('affiliates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')
                ->constrained('tenants')
                ->cascadeOnDelete();
            $table->string('name', 150);
            $table->string('email', 150);
            $table->string('phone', 30);
            $table->string('commission_type', 20);
            $table->decimal('commission_value', 12, 2);
            $table->jsonb('payment_info')->default('{}');
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();

            $table->unique(['tenant_id', 'email']);
            $table->index(['tenant_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('affiliates');
    }
};
