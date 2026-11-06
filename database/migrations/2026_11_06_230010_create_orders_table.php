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
        Schema::create('orders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')
                ->constrained('tenants')
                ->cascadeOnDelete();
            $table->string('order_number', 30);
            $table->string('status', 30)->default('PENDIENTE_PAGO');
            $table->jsonb('customer_info');
            $table->jsonb('shipping_address');
            $table->decimal('subtotal', 12, 2);
            $table->decimal('auto_discount_total', 12, 2)->default(0.00);
            $table->decimal('coupon_discount_total', 12, 2)->default(0.00);
            $table->decimal('shipping_fee', 12, 2)->default(0.00);
            $table->decimal('total_net', 12, 2);
            $table->foreignUuid('applied_promotion_id')
                ->nullable()
                ->constrained('promotions')
                ->nullOnDelete();
            $table->foreignUuid('applied_coupon_id')
                ->nullable()
                ->constrained('coupons')
                ->nullOnDelete();
            $table->text('whatsapp_url');
            $table->timestampTz('completed_at')->nullable();
            $table->timestampTz('cancelled_at')->nullable();
            $table->timestampsTz();

            $table->unique(['tenant_id', 'order_number']);
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'created_at']);
            $table->index(['tenant_id', 'applied_promotion_id']);
            $table->index(['tenant_id', 'applied_coupon_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
