<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ProductVariant>
 */
class ProductVariantFactory extends Factory
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
            'product_id' => Product::factory(),
            'sku' => strtoupper(Str::random(8)),
            'price' => null,
            'stock' => fake()->numberBetween(0, 100),
            'metadata' => [],
            'is_default' => false,
            'is_active' => true,
        ];
    }
}
