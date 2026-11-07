<?php

use App\Context\TenantContext;
use App\Models\Affiliate;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\Promotion;
use App\Models\Tenant;
use App\Models\TenantConfig;
use App\Services\Checkout\CheckoutOrchestratorService;
use App\Services\Checkout\WhatsAppOrderMessageBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->tenant = Tenant::factory()->create(['slug' => fake()->unique()->slug(), 'is_active' => true]);
    app(TenantContext::class)->set($this->tenant);
});

test('generador de whatsapp codifica correctamente caracteres especiales y saltos de linea', function () {
    $builder = app(WhatsAppOrderMessageBuilder::class);

    $config = TenantConfig::factory()->create([
        'tenant_id' => $this->tenant->id,
        'contact_info' => [
            'phone' => '+504 9900-1122',
            'whatsapp_sales' => '+504 9988-7766',
            'bank_accounts' => [
                ['bank' => 'Banco Ficohsa', 'account' => '2000-1234-5678', 'type' => 'Cheques', 'holder' => 'Mi Tienda S. de R.L.'],
            ],
        ],
    ]);

    $customerInfo = [
        'full_name' => 'Carlos Mendoza & Co.',
        'whatsapp_phone' => '+50498765432',
        'email' => 'carlos@correo.com',
    ];

    $shippingAddress = [
        'region_code' => 'HN-CR',
        'address_line' => 'Col. Trejo, #42',
        'reference' => 'Frente al parque',
    ];

    $pricing = [
        'subtotal' => 4450.00,
        'auto_discount_total' => 0.00,
        'coupon_discount_total' => 445.00,
        'coupon_code' => 'PROMOVERANO',
        'shipping_fee' => 80.00,
        'shipping_zone_name' => 'Cortés',
        'total_net' => 4085.00,
        'items' => [
            [
                'product_name' => 'Zapato Oxford Clásico',
                'quantity' => 2,
                'unit_price' => 1450.00,
                'line_total' => 2900.00,
                'variant_attributes' => ['talla' => '39', 'color' => 'Negro'],
            ],
        ],
    ];

    $url = $builder->buildRedirectUrl(
        tenant: $this->tenant,
        orderNumber: '#ORD-8392K1',
        customerInfo: $customerInfo,
        shippingAddress: $shippingAddress,
        pricing: $pricing,
        config: $config
    );

    expect($url)->toStartWith('https://wa.me/50499887766?text=')
        ->and($url)->toContain(rawurlencode('#ORD-8392K1'))
        ->and($url)->toContain(rawurlencode('Carlos Mendoza & Co.'))
        ->and($url)->toContain(rawurlencode('Banco Ficohsa'))
        ->and($url)->not->toContain(' ');
});

test('calculo de comisiones de afiliado maneja esquemas porcentuales y montos fijos', function () {
    $service = app(CheckoutOrchestratorService::class);

    $affiliatePercent = Affiliate::factory()->create([
        'tenant_id' => $this->tenant->id,
        'commission_type' => 'PERCENTAGE',
        'commission_value' => 10.00,
        'is_active' => true,
    ]);

    $affiliateFixed = Affiliate::factory()->create([
        'tenant_id' => $this->tenant->id,
        'commission_type' => 'FIXED_AMOUNT',
        'commission_value' => 150.00,
        'is_active' => true,
    ]);

    $promo = Promotion::factory()->create([
        'tenant_id' => $this->tenant->id,
        'type' => 'COUPON',
    ]);

    $couponPercent = Coupon::factory()->create([
        'tenant_id' => $this->tenant->id,
        'promotion_id' => $promo->id,
        'affiliate_id' => $affiliatePercent->id,
        'code' => 'AFFPERCENT',
    ]);

    $couponFixed = Coupon::factory()->create([
        'tenant_id' => $this->tenant->id,
        'promotion_id' => $promo->id,
        'affiliate_id' => $affiliateFixed->id,
        'code' => 'AFFFIXED',
    ]);

    $order1 = Order::factory()->create([
        'tenant_id' => $this->tenant->id,
        'subtotal' => 2000.00,
    ]);

    $order2 = Order::factory()->create([
        'tenant_id' => $this->tenant->id,
        'subtotal' => 2000.00,
    ]);

    $commission1 = $service->recordAffiliateCommission($order1, $couponPercent, 2000.00);
    $commission2 = $service->recordAffiliateCommission($order2, $couponFixed, 2000.00);

    expect((float) $commission1?->amount)->toBe(200.00)
        ->and($commission1?->status)->toBe('RETENIDA')
        ->and((float) $commission2?->amount)->toBe(150.00)
        ->and($commission2?->status)->toBe('RETENIDA');
});

test('generador de correlativo de orden produce codigos de 6 caracteres unicos', function () {
    $service = app(CheckoutOrchestratorService::class);

    $code1 = $service->generateUniqueOrderNumber();
    $code2 = $service->generateUniqueOrderNumber();

    expect($code1)->toMatch('/^#ORD-[A-Z0-9]{6}$/')
        ->and($code2)->toMatch('/^#ORD-[A-Z0-9]{6}$/')
        ->and($code1)->not->toBe($code2);
});
