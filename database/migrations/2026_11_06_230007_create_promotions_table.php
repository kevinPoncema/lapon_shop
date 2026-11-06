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
        Schema::create('promotions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')
                ->constrained('tenants')
                ->cascadeOnDelete();
            $table->string('name', 150);
            $table->string('type', 20);
            $table->string('discount_type', 20);
            $table->decimal('discount_value', 12, 2);
            $table->jsonb('conditions')->default('{}');
            $table->boolean('allow_stacking')->default(false);
            $table->timestampTz('starts_at');
            $table->timestampTz('ends_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();

            $table->index(['tenant_id', 'is_active']);
            $table->index(['tenant_id', 'type']);
            $table->index(['tenant_id', 'starts_at', 'ends_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('promotions');
    }
};
