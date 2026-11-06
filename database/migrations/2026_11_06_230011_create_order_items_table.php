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
        Schema::create('order_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')
                ->constrained('tenants')
                ->cascadeOnDelete();
            $table->foreignUuid('order_id')
                ->constrained('orders')
                ->cascadeOnDelete();
            $table->foreignUuid('product_id')
                ->constrained('products')
                ->restrictOnDelete();
            $table->foreignUuid('variant_id')
                ->constrained('product_variants')
                ->restrictOnDelete();
            $table->integer('quantity');
            $table->decimal('unit_price', 12, 2);
            $table->decimal('line_total', 12, 2);
            $table->jsonb('variant_snapshot');
            $table->timestampTz('created_at')->useCurrent();

            $table->index(['tenant_id', 'order_id']);
            $table->index(['tenant_id', 'product_id']);
            $table->index(['tenant_id', 'variant_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
