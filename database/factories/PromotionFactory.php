<?php

namespace Database\Factories;

use App\Models\Promotion;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Promotion>
 */
class PromotionFactory extends Factory
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
            'name' => fake()->words(3, true),
            'type' => 'AUTOMATIC',
            'discount_type' => 'PERCENTAGE',
            'discount_value' => fake()->randomFloat(2, 5, 50),
            'conditions' => [],
            'allow_stacking' => false,
            'starts_at' => now(),
            'ends_at' => now()->addMonths(1),
            'is_active' => true,
        ];
    }
}
