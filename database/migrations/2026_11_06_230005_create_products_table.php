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
        Schema::create('products', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')
                ->constrained('tenants')
                ->cascadeOnDelete();
            $table->foreignUuid('category_id')
                ->nullable()
                ->constrained('categories')
                ->nullOnDelete();
            $table->string('name', 180);
            $table->string('slug', 200);
            $table->text('description')->nullable();
            $table->decimal('base_price', 12, 2);
            $table->jsonb('metadata')->default('{}');
            $table->jsonb('images')->default('[]');
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->unique(['tenant_id', 'slug']);
            $table->index(['tenant_id', 'category_id']);
            $table->index(['tenant_id', 'is_active']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('CREATE INDEX products_metadata_gin ON products USING gin (metadata jsonb_path_ops)');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS products_metadata_gin');
        }

        Schema::dropIfExists('products');
    }
};
