<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('coupons', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')
                ->constrained('tenants')
                ->cascadeOnDelete();
            $table->foreignUuid('promotion_id')
                ->constrained('promotions')
                ->cascadeOnDelete();
            $table->foreignUuid('affiliate_id')
                ->nullable()
                ->constrained('affiliates')
                ->nullOnDelete();
            $table->string('code', 50);
            $table->integer('max_uses')->nullable();
            $table->integer('used_count')->default(0);
            $table->timestampTz('starts_at');
            $table->timestampTz('ends_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();

            $table->unique(['tenant_id', 'code']);
            $table->index(['tenant_id', 'promotion_id']);
            $table->index(['tenant_id', 'affiliate_id']);
            $table->index(['tenant_id', 'is_active']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE coupons ADD CONSTRAINT coupons_usage_check CHECK (max_uses IS NULL OR used_count <= max_uses)');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('coupons');
    }
};
