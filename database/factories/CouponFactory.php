<?php

namespace Database\Factories;

use App\Models\Coupon;
use App\Models\Promotion;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Coupon>
 */
class CouponFactory extends Factory
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
            'promotion_id' => Promotion::factory(),
            'affiliate_id' => null,
            'code' => strtoupper(Str::random(8)),
            'max_uses' => 100,
            'used_count' => 0,
            'starts_at' => now(),
            'ends_at' => now()->addMonths(1),
            'is_active' => true,
        ];
    }
}
