<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderItem>
 */
class OrderItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $price = fake()->randomFloat(2, 50, 500);
        $qty = fake()->numberBetween(1, 3);

        return [
            'tenant_id' => Tenant::factory(),
            'order_id' => Order::factory(),
            'product_id' => Product::factory(),
            'variant_id' => ProductVariant::factory(),
            'quantity' => $qty,
            'unit_price' => $price,
            'line_total' => $price * $qty,
            'variant_snapshot' => ['sku' => 'TEST-SKU'],
        ];
    }
}
