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
        Schema::create('outbound_rewards', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')
                ->constrained('tenants')
                ->cascadeOnDelete();
            $table->foreignUuid('order_id')
                ->constrained('orders')
                ->cascadeOnDelete();
            $table->foreignUuid('partner_store_id')
                ->constrained('partner_stores')
                ->cascadeOnDelete();
            $table->string('code', 64)->unique();
            $table->string('status', 20)->default('EMITIDO');
            $table->timestampTz('expires_at');
            $table->timestampTz('redeemed_at')->nullable();
            $table->timestampTz('email_sent_at')->nullable();
            $table->timestampsTz();

            $table->unique(['order_id', 'partner_store_id']);
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'partner_store_id']);
            $table->index(['tenant_id', 'expires_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('outbound_rewards');
    }
};
