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
        Schema::create('partner_stores', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')
                ->constrained('tenants')
                ->cascadeOnDelete();
            $table->string('name', 150);
            $table->string('portal_email', 150);
            $table->string('portal_password', 255);
            $table->string('benefit_title', 200);
            $table->text('terms_conditions');
            $table->jsonb('contact_info')->default('{}');
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();

            $table->unique(['tenant_id', 'portal_email']);
            $table->index(['tenant_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('partner_stores');
    }
};
