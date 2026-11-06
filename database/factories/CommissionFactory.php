<?php

namespace Database\Factories;

use App\Models\Affiliate;
use App\Models\Commission;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Commission>
 */
class CommissionFactory extends Factory
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
            'affiliate_id' => Affiliate::factory(),
            'coupon_id' => Coupon::factory(),
            'amount' => fake()->randomFloat(2, 10, 100),
            'status' => 'RETENIDA',
            'paid_at' => null,
            'payment_reference' => null,
            'payment_receipt_url' => null,
        ];
    }
}
