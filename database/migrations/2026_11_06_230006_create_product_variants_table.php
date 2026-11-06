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
        Schema::create('product_variants', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')
                ->constrained('tenants')
                ->cascadeOnDelete();
            $table->foreignUuid('product_id')
                ->constrained('products')
                ->cascadeOnDelete();
            $table->string('sku', 80);
            $table->decimal('price', 12, 2)->nullable();
            $table->integer('stock');
            $table->jsonb('metadata')->default('{}');
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->unique(['tenant_id', 'sku']);
            $table->index(['tenant_id', 'product_id']);
            $table->index(['tenant_id', 'is_active']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE product_variants ADD CONSTRAINT product_variants_stock_check CHECK (stock >= 0)');
            DB::statement('CREATE INDEX product_variants_metadata_gin ON product_variants USING gin (metadata jsonb_path_ops)');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS product_variants_metadata_gin');
        }

        Schema::dropIfExists('product_variants');
    }
};
