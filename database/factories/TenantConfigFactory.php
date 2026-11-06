<?php

namespace Database\Factories;

use App\Models\Tenant;
use App\Models\TenantConfig;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TenantConfig>
 */
class TenantConfigFactory extends Factory
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
            'branding' => [
                'primary_color' => fake()->hexColor(),
                'logo_url' => fake()->imageUrl(),
            ],
            'contact_info' => [
                'phone' => fake()->phoneNumber(),
                'whatsapp' => fake()->phoneNumber(),
            ],
            'shipping_zones' => [
                ['code' => 'HN-CR', 'name' => 'Cortés', 'fee' => 80.00],
            ],
            'metadata_schema' => [],
            'filter_config' => [],
            'outbound_reward_rules' => [],
        ];
    }
}
