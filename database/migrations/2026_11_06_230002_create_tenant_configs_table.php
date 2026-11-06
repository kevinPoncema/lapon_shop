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
        Schema::create('tenant_configs', function (Blueprint $table) {
            $table->foreignUuid('tenant_id')
                ->primary()
                ->constrained('tenants')
                ->cascadeOnDelete();
            $table->jsonb('branding')->default('{}');
            $table->jsonb('contact_info')->default('{}');
            $table->jsonb('shipping_zones')->default('[]');
            $table->jsonb('metadata_schema')->default('[]');
            $table->jsonb('filter_config')->default('[]');
            $table->jsonb('outbound_reward_rules')->default('{}');
            $table->timestampTz('updated_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenant_configs');
    }
};
