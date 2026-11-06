<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\OutboundReward;
use App\Models\PartnerStore;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<OutboundReward>
 */
class OutboundRewardFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'order_id' => Order::factory(),
            'partner_store_id' => PartnerStore::factory(),
            'code' => 'RWD-'.strtoupper(Str::random(12)),
            'status' => 'EMITIDO',
            'expires_at' => now()->addDays(30),
            'redeemed_at' => null,
            'email_sent_at' => null,
        ];
    }
}
