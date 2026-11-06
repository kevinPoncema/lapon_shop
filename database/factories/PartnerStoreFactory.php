<?php

namespace Database\Factories;

use App\Models\PartnerStore;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<PartnerStore>
 */
class PartnerStoreFactory extends Factory
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
            'name' => fake()->company(),
            'portal_email' => fake()->unique()->safeEmail(),
            'portal_password' => Hash::make('secret'),
            'benefit_title' => '15% de descuento en toda la tienda',
            'terms_conditions' => fake()->paragraph(),
            'contact_info' => ['address' => fake()->address()],
            'is_active' => true,
        ];
    }
}
