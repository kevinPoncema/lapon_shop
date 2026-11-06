<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $subtotal = fake()->randomFloat(2, 200, 3000);
        $shipping = 80.00;

        return [
            'tenant_id' => Tenant::factory(),
            'order_number' => '#ORD-'.fake()->unique()->randomNumber(5),
            'status' => 'PENDIENTE_PAGO',
            'customer_info' => [
                'name' => fake()->name(),
                'email' => fake()->safeEmail(),
                'phone' => fake()->phoneNumber(),
            ],
            'shipping_address' => [
                'region_code' => 'HN-CR',
                'address' => fake()->streetAddress(),
            ],
            'subtotal' => $subtotal,
            'auto_discount_total' => 0.00,
            'coupon_discount_total' => 0.00,
            'shipping_fee' => $shipping,
            'total_net' => $subtotal + $shipping,
            'applied_promotion_id' => null,
            'applied_coupon_id' => null,
            'whatsapp_url' => 'https://wa.me/50499000000',
            'completed_at' => null,
            'cancelled_at' => null,
        ];
    }
}
