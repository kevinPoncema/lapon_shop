<?php

use App\Context\TenantContext;
use App\Models\Affiliate;
use App\Models\Category;
use App\Models\Commission;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\OutboundReward;
use App\Models\PartnerStore;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Promotion;
use App\Models\Tenant;
use App\Models\TenantConfig;
use App\Models\User;
use Database\Seeders\DemoTenantSeeder;

it('upserts the demo tenant with metadata schema, filters, catalog, marketing, and sample orders idempotently', function () {
    $this->seed(DemoTenantSeeder::class);
    $this->seed(DemoTenantSeeder::class);

    $tenant = Tenant::query()->where('slug', 'kinetic-wear')->first();

    expect($tenant)->not->toBeNull()
        ->and(Tenant::query()->where('slug', 'kinetic-wear')->count())->toBe(1);

    /** @var Tenant $tenant */
    app(TenantContext::class)->executeAs($tenant, function (Tenant $activeTenant): void {
        $config = TenantConfig::query()->where('tenant_id', $activeTenant->id)->first();

        expect($config)->not->toBeNull()
            ->and($config?->metadata_schema)->toHaveCount(6)
            ->and($config?->filter_config)->toHaveCount(6)
            ->and($config?->shipping_zones)->toHaveCount(4)
            ->and(User::query()->count())->toBe(2)
            ->and(PartnerStore::query()->count())->toBe(2)
            ->and(Category::query()->count())->toBe(6)
            ->and(Product::query()->count())->toBe(6)
            ->and(ProductVariant::query()->count())->toBe(13)
            ->and(Promotion::query()->count())->toBe(4)
            ->and(Affiliate::query()->count())->toBe(2)
            ->and(Coupon::query()->count())->toBe(2)
            ->and(Order::query()->count())->toBe(2)
            ->and(Commission::query()->count())->toBe(2)
            ->and(OutboundReward::query()->where('code', 'RWD-KINE-CAFE-2026')->count())->toBe(1);
    });

    $this->withHeader('X-Tenant-Slug', 'kinetic-wear')
        ->getJson('/api/v1/storefront/config')
        ->assertOk()
        ->assertJsonPath('tenant.name', 'Kinetic Wear HN')
        ->assertJsonCount(6, 'filters');

    $this->withHeader('X-Tenant-Slug', 'kinetic-wear')
        ->getJson('/api/v1/storefront/products?attr[impermeable]=true&attr[talla]=M')
        ->assertOk()
        ->assertJsonCount(3, 'data');
});
