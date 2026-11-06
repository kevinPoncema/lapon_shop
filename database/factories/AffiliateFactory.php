<?php

namespace Database\Factories;

use App\Models\Affiliate;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Affiliate>
 */
class AffiliateFactory extends Factory
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
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->phoneNumber(),
            'commission_type' => 'PERCENTAGE',
            'commission_value' => fake()->randomFloat(2, 5, 20),
            'payment_info' => ['bank' => 'BAC', 'account' => fake()->numerify('##########')],
            'is_active' => true,
        ];
    }
}
