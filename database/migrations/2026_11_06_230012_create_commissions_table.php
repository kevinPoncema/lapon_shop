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
        Schema::create('commissions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')
                ->constrained('tenants')
                ->cascadeOnDelete();
            $table->foreignUuid('order_id')
                ->constrained('orders')
                ->cascadeOnDelete();
            $table->foreignUuid('affiliate_id')
                ->constrained('affiliates')
                ->cascadeOnDelete();
            $table->foreignUuid('coupon_id')
                ->constrained('coupons')
                ->cascadeOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('status', 20)->default('RETENIDA');
            $table->timestampTz('paid_at')->nullable();
            $table->string('payment_reference', 120)->nullable();
            $table->string('payment_receipt_url', 255)->nullable();
            $table->timestampsTz();

            $table->unique(['tenant_id', 'order_id']);
            $table->index(['tenant_id', 'affiliate_id']);
            $table->index(['tenant_id', 'coupon_id']);
            $table->index(['tenant_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('commissions');
    }
};
