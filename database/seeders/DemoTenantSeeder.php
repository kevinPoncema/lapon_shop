<?php

namespace Database\Seeders;

use App\Context\TenantContext;
use App\Models\Affiliate;
use App\Models\Category;
use App\Models\Commission;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OutboundReward;
use App\Models\PartnerStore;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Promotion;
use App\Models\Tenant;
use App\Models\TenantConfig;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class DemoTenantSeeder extends Seeder
{
    /**
     * Upsert completo e idempotente de un inquilino demostrativo ("Kinetic Wear HN")
     * con esquema de metadatos, filtros facetados, catálogo jerárquico, variantes,
     * promociones, afiliados, comercios aliados y órdenes/vouchers de ejemplo.
     */
    public function run(): void
    {
        $tenant = DB::transaction(function (): Tenant {
            $tenant = Tenant::query()->updateOrCreate(
                ['slug' => 'kinetic-wear'],
                [
                    'name' => 'Kinetic Wear HN',
                    'country_code' => 'HN',
                    'currency_code' => 'HNL',
                    'is_active' => true,
                ]
            );

            app(TenantContext::class)->executeAs($tenant, function (Tenant $activeTenant): void {
                $this->upsertAdminUsers($activeTenant);
                $partners = $this->upsertPartnerStores($activeTenant);
                $this->upsertTenantConfiguration($activeTenant, $partners['espresso']);
                $categories = $this->upsertCategories($activeTenant);
                $variants = $this->upsertCatalogProductsAndVariants($activeTenant, $categories);
                $marketing = $this->upsertMarketingAndAffiliates($activeTenant);
                $this->upsertSampleOrdersAndRewards(
                    $activeTenant,
                    $variants,
                    $marketing,
                    $partners['espresso']
                );
            });

            return $tenant;
        });

        Cache::forget("tenant:slug:{$tenant->slug}");
        Cache::forget("tenant:{$tenant->id}:storefront_config");
        Cache::forget("tenant:{$tenant->id}:storefront_categories");
    }

    private function upsertAdminUsers(Tenant $tenant): void
    {
        User::query()->updateOrCreate(
            [
                'tenant_id' => $tenant->id,
                'email' => 'admin@kineticwear.hn',
            ],
            [
                'name' => 'Valeria Paz (Owner)',
                'password' => 'password',
                'role' => 'OWNER',
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );

        User::query()->updateOrCreate(
            [
                'tenant_id' => $tenant->id,
                'email' => 'operaciones@kineticwear.hn',
            ],
            [
                'name' => 'Diego Mendoza (Staff)',
                'password' => 'password',
                'role' => 'STAFF',
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );
    }

    /**
     * @return array{espresso: PartnerStore, altitude: PartnerStore}
     */
    private function upsertPartnerStores(Tenant $tenant): array
    {
        $espresso = PartnerStore::query()->updateOrCreate(
            [
                'tenant_id' => $tenant->id,
                'portal_email' => 'caja@espressolab.hn',
            ],
            [
                'name' => 'Espresso Lab Specialty Coffee',
                'portal_password' => 'password',
                'benefit_title' => 'Cold Brew Nitro de Cortesía o 25% OFF en Barra',
                'terms_conditions' => 'Válido por 1 canje presentando el código RWD en mostrador de Espresso Lab.',
                'contact_info' => [
                    'address' => 'Blvd. Morazán, Tegucigalpa',
                    'phone' => '+50422359900',
                    'schedule' => 'Lun-Sáb 7:00 AM - 8:00 PM',
                ],
                'is_active' => true,
            ]
        );

        $altitude = PartnerStore::query()->updateOrCreate(
            [
                'tenant_id' => $tenant->id,
                'portal_email' => 'recepcion@altitude.hn',
            ],
            [
                'name' => 'Altitude Functional Studio',
                'portal_password' => 'password',
                'benefit_title' => 'Pase Recovery Lounge + Batido Proteico Gratis',
                'terms_conditions' => 'Presentar código RWD vigente en recepción previa reserva.',
                'contact_info' => [
                    'address' => 'Col. Lomas del Guijarro, Tegucigalpa',
                    'phone' => '+50494501122',
                    'schedule' => 'Lun-Sab 5:30 AM - 9:00 PM',
                ],
                'is_active' => true,
            ]
        );

        return [
            'espresso' => $espresso,
            'altitude' => $altitude,
        ];
    }

    private function upsertTenantConfiguration(Tenant $tenant, PartnerStore $rewardPartner): void
    {
        TenantConfig::query()->updateOrCreate(
            ['tenant_id' => $tenant->id],
            [
                'branding' => [
                    'primary_color' => '#6366F1',
                    'secondary_color' => '#18181B',
                    'accent_color' => '#10B981',
                    'font_family' => 'Instrument Sans',
                    'social_links' => [
                        'whatsapp' => 'https://wa.me/50499887766',
                        'instagram' => 'https://instagram.com/kineticwear.hn',
                        'tiktok' => 'https://tiktok.com/@kineticwear.hn',
                    ],
                ],
                'contact_info' => [
                    'whatsapp_sales' => '50499887766',
                    'support_email' => 'soporte@kineticwear.hn',
                    'bank_accounts' => [
                        [
                            'bank_name' => 'BAC Credomatic',
                            'account_number' => '748291044',
                            'account_type' => 'Cheques HNL',
                            'holder_name' => 'Kinetic Wear S. de R.L.',
                        ],
                        [
                            'bank_name' => 'Banco Atlántida',
                            'account_number' => '1100492810',
                            'account_type' => 'Ahorros HNL',
                            'holder_name' => 'Kinetic Wear S. de R.L.',
                        ],
                    ],
                ],
                'shipping_zones' => [
                    ['code' => 'TGU', 'name' => 'Tegucigalpa — Entrega Express Mismo Día', 'fee' => 85.00],
                    ['code' => 'SPS', 'name' => 'San Pedro Sula — Courier 24h', 'fee' => 130.00],
                    ['code' => 'CBA', 'name' => 'La Ceiba / Roatán — Aéreo 48h', 'fee' => 195.00],
                    ['code' => 'PICKUP', 'name' => 'Retiro en Showroom Blvd. Morazán', 'fee' => 0.00],
                ],
                'metadata_schema' => [
                    [
                        'key' => 'talla',
                        'label' => 'Talla',
                        'data_type' => 'select',
                        'options' => ['XS', 'S', 'M', 'L', 'XL'],
                        'is_required' => true,
                        'affects_stock' => true,
                    ],
                    [
                        'key' => 'color',
                        'label' => 'Color',
                        'data_type' => 'select',
                        'options' => ['Obsidian', 'Indigo', 'Titanium', 'Emerald', 'Sand'],
                        'is_required' => true,
                        'affects_stock' => true,
                    ],
                    [
                        'key' => 'material',
                        'label' => 'Tejido Técnico',
                        'data_type' => 'select',
                        'options' => ['Ripstop Nylon', 'Merino Tech', 'Cordura Lite', 'AeroMesh 3D', 'Polartec Alpha'],
                        'is_required' => true,
                        'affects_stock' => false,
                    ],
                    [
                        'key' => 'fit_corte',
                        'label' => 'Calce / Fit',
                        'data_type' => 'select',
                        'options' => ['Athletic Fit', 'Relaxed Tech', 'Compression'],
                        'is_required' => false,
                        'affects_stock' => false,
                    ],
                    [
                        'key' => 'impermeable',
                        'label' => 'Membrana Impermeable DWR',
                        'data_type' => 'boolean',
                        'options' => null,
                        'is_required' => false,
                        'affects_stock' => false,
                    ],
                    [
                        'key' => 'peso_gramos',
                        'label' => 'Peso Ultraligero (g)',
                        'data_type' => 'number',
                        'options' => null,
                        'is_required' => false,
                        'affects_stock' => false,
                    ],
                ],
                'filter_config' => [
                    [
                        'type' => 'system',
                        'key' => 'price',
                        'label' => 'Rango de Precio',
                        'data_type' => 'range',
                        'order' => 0,
                    ],
                    [
                        'type' => 'metadata',
                        'key' => 'talla',
                        'label' => 'Talla',
                        'data_type' => 'select',
                        'order' => 1,
                    ],
                    [
                        'type' => 'metadata',
                        'key' => 'color',
                        'label' => 'Color',
                        'data_type' => 'select',
                        'order' => 2,
                    ],
                    [
                        'type' => 'metadata',
                        'key' => 'material',
                        'label' => 'Tejido Técnico',
                        'data_type' => 'select',
                        'order' => 3,
                    ],
                    [
                        'type' => 'metadata',
                        'key' => 'fit_corte',
                        'label' => 'Calce / Fit',
                        'data_type' => 'select',
                        'order' => 4,
                    ],
                    [
                        'type' => 'metadata',
                        'key' => 'impermeable',
                        'label' => 'Membrana Impermeable DWR',
                        'data_type' => 'boolean',
                        'order' => 5,
                    ],
                ],
                'outbound_reward_rules' => [
                    'is_enabled' => true,
                    'partner_store_id' => $rewardPartner->id,
                    'min_order_amount' => 900,
                    'validity_days' => 30,
                ],
            ]
        );
    }

    /**
     * @return array<string, Category>
     */
    private function upsertCategories(Tenant $tenant): array
    {
        $outerwear = Category::query()->updateOrCreate(
            ['tenant_id' => $tenant->id, 'slug' => 'outerwear-tecnico'],
            ['name' => 'Outerwear Técnico', 'parent_id' => null, 'is_active' => true]
        );

        $shells = Category::query()->updateOrCreate(
            ['tenant_id' => $tenant->id, 'slug' => 'chaquetas-impermeables'],
            ['name' => 'Chaquetas Impermeables', 'parent_id' => $outerwear->id, 'is_active' => true]
        );

        $performance = Category::query()->updateOrCreate(
            ['tenant_id' => $tenant->id, 'slug' => 'entrenamiento-performance'],
            ['name' => 'Performance & Running', 'parent_id' => null, 'is_active' => true]
        );

        $tops = Category::query()->updateOrCreate(
            ['tenant_id' => $tenant->id, 'slug' => 'tops-termorreguladores'],
            ['name' => 'Tops Termorreguladores', 'parent_id' => $performance->id, 'is_active' => true]
        );

        $bottoms = Category::query()->updateOrCreate(
            ['tenant_id' => $tenant->id, 'slug' => 'bottoms-urbanos'],
            ['name' => 'Bottoms & Cargo Táctico', 'parent_id' => null, 'is_active' => true]
        );

        $gear = Category::query()->updateOrCreate(
            ['tenant_id' => $tenant->id, 'slug' => 'gear-accesorios'],
            ['name' => 'Gear & Mochilas Modulares', 'parent_id' => null, 'is_active' => true]
        );

        return [
            'outerwear' => $outerwear,
            'shells' => $shells,
            'performance' => $performance,
            'tops' => $tops,
            'bottoms' => $bottoms,
            'gear' => $gear,
        ];
    }

    /**
     * @param  array<string, Category>  $categories
     * @return array<string, ProductVariant>
     */
    private function upsertCatalogProductsAndVariants(Tenant $tenant, array $categories): array
    {
        $catalog = [
            [
                'slug' => 'chaqueta-kinetic-shell-v2',
                'category_id' => $categories['shells']->id,
                'name' => 'Chaqueta Kinetic Shell V2',
                'description' => 'Rompevientos ultraligero de 3 capas con costuras termoselladas y ventilación láser para clima urbano variable.',
                'base_price' => 1890.00,
                'metadata' => [
                    'material' => 'Ripstop Nylon',
                    'fit_corte' => 'Athletic Fit',
                    'impermeable' => true,
                    'peso_gramos' => 245,
                ],
                'images' => [
                    'https://images.unsplash.com/photo-1591047139829-d91aecb6caea?auto=format&fit=crop&w=900&q=80',
                ],
                'variants' => [
                    ['sku' => 'KW-SHL-OBS-M', 'price' => null, 'stock' => 14, 'is_default' => true, 'metadata' => ['talla' => 'M', 'color' => 'Obsidian']],
                    ['sku' => 'KW-SHL-IND-L', 'price' => 1950.00, 'stock' => 9, 'is_default' => false, 'metadata' => ['talla' => 'L', 'color' => 'Indigo']],
                    ['sku' => 'KW-SHL-EME-XL', 'price' => 1950.00, 'stock' => 6, 'is_default' => false, 'metadata' => ['talla' => 'XL', 'color' => 'Emerald']],
                ],
            ],
            [
                'slug' => 'camiseta-aero-merino-pro',
                'category_id' => $categories['tops']->id,
                'name' => 'Camiseta Aero Merino Pro',
                'description' => 'Tejido termorregulador antibacteriano de secado rápido para sesiones de alta intensidad y trail.',
                'base_price' => 790.00,
                'metadata' => [
                    'material' => 'Merino Tech',
                    'fit_corte' => 'Athletic Fit',
                    'impermeable' => false,
                    'peso_gramos' => 135,
                ],
                'images' => [
                    'https://images.unsplash.com/photo-1521572163474-6864f9cf17ab?auto=format&fit=crop&w=900&q=80',
                ],
                'variants' => [
                    ['sku' => 'KW-TEE-TIT-M', 'price' => null, 'stock' => 22, 'is_default' => true, 'metadata' => ['talla' => 'M', 'color' => 'Titanium']],
                    ['sku' => 'KW-TEE-EME-S', 'price' => null, 'stock' => 11, 'is_default' => false, 'metadata' => ['talla' => 'S', 'color' => 'Emerald']],
                    ['sku' => 'KW-TEE-OBS-L', 'price' => null, 'stock' => 18, 'is_default' => false, 'metadata' => ['talla' => 'L', 'color' => 'Obsidian']],
                ],
            ],
            [
                'slug' => 'jogger-cordura-tactico',
                'category_id' => $categories['bottoms']->id,
                'name' => 'Jogger Cordura Táctico 4-Way',
                'description' => 'Pantalón articulado con bolsillos magnéticos ocultos, refuerzo elástico en rodilla y repelencia DWR.',
                'base_price' => 1350.00,
                'metadata' => [
                    'material' => 'Cordura Lite',
                    'fit_corte' => 'Relaxed Tech',
                    'impermeable' => true,
                    'peso_gramos' => 340,
                ],
                'images' => [
                    'https://images.unsplash.com/photo-1552902865-b72c031ac5ea?auto=format&fit=crop&w=900&q=80',
                ],
                'variants' => [
                    ['sku' => 'KW-JOG-OBS-M', 'price' => null, 'stock' => 16, 'is_default' => true, 'metadata' => ['talla' => 'M', 'color' => 'Obsidian']],
                    ['sku' => 'KW-JOG-SND-L', 'price' => 1390.00, 'stock' => 10, 'is_default' => false, 'metadata' => ['talla' => 'L', 'color' => 'Sand']],
                ],
            ],
            [
                'slug' => 'hoodie-polartec-alpha-grid',
                'category_id' => $categories['outerwear']->id,
                'name' => 'Hoodie Polartec Alpha Grid',
                'description' => 'Capa térmica activa con micro-celdas de retención de calor y paneles laterales transpirables.',
                'base_price' => 1580.00,
                'metadata' => [
                    'material' => 'Polartec Alpha',
                    'fit_corte' => 'Relaxed Tech',
                    'impermeable' => false,
                    'peso_gramos' => 290,
                ],
                'images' => [
                    'https://images.unsplash.com/photo-1556821840-3a63f95609a7?auto=format&fit=crop&w=900&q=80',
                ],
                'variants' => [
                    ['sku' => 'KW-HOD-IND-M', 'price' => null, 'stock' => 12, 'is_default' => true, 'metadata' => ['talla' => 'M', 'color' => 'Indigo']],
                    ['sku' => 'KW-HOD-OBS-L', 'price' => null, 'stock' => 8, 'is_default' => false, 'metadata' => ['talla' => 'L', 'color' => 'Obsidian']],
                ],
            ],
            [
                'slug' => 'short-endurance-2en1-pro',
                'category_id' => $categories['performance']->id,
                'name' => 'Short Endurance 2-en-1 Liner Pro',
                'description' => 'Short exterior perforado por láser con liner compresivo interno y bolsillo anti-rebote para smartphone.',
                'base_price' => 920.00,
                'metadata' => [
                    'material' => 'AeroMesh 3D',
                    'fit_corte' => 'Compression',
                    'impermeable' => false,
                    'peso_gramos' => 165,
                ],
                'images' => [
                    'https://images.unsplash.com/photo-1591195853828-11db59a44f6b?auto=format&fit=crop&w=900&q=80',
                ],
                'variants' => [
                    ['sku' => 'KW-SHT-OBS-M', 'price' => null, 'stock' => 19, 'is_default' => true, 'metadata' => ['talla' => 'M', 'color' => 'Obsidian']],
                    ['sku' => 'KW-SHT-TIT-L', 'price' => null, 'stock' => 14, 'is_default' => false, 'metadata' => ['talla' => 'L', 'color' => 'Titanium']],
                ],
            ],
            [
                'slug' => 'mochila-modular-commuter-22l',
                'category_id' => $categories['gear']->id,
                'name' => 'Mochila Modular Commuter 22L',
                'description' => 'Compartimento suspendido para laptop 16", cierres sellados YKK Aquaguard y herrajes magnéticos.',
                'base_price' => 2450.00,
                'metadata' => [
                    'material' => 'Cordura Lite',
                    'fit_corte' => 'Relaxed Tech',
                    'impermeable' => true,
                    'peso_gramos' => 780,
                ],
                'images' => [
                    'https://images.unsplash.com/photo-1553062407-98eeb64c6a62?auto=format&fit=crop&w=900&q=80',
                ],
                'variants' => [
                    ['sku' => 'KW-BAG-OBS-M', 'price' => null, 'stock' => 7, 'is_default' => true, 'metadata' => ['talla' => 'M', 'color' => 'Obsidian']],
                ],
            ],
        ];

        /** @var array<string, ProductVariant> $indexedVariants */
        $indexedVariants = [];

        foreach ($catalog as $item) {
            $product = Product::query()->updateOrCreate(
                ['tenant_id' => $tenant->id, 'slug' => $item['slug']],
                [
                    'category_id' => $item['category_id'],
                    'name' => $item['name'],
                    'description' => $item['description'],
                    'base_price' => $item['base_price'],
                    'metadata' => $item['metadata'],
                    'images' => $item['images'],
                    'is_active' => true,
                ]
            );

            foreach ($item['variants'] as $variantData) {
                $variant = ProductVariant::query()->updateOrCreate(
                    ['tenant_id' => $tenant->id, 'sku' => $variantData['sku']],
                    [
                        'product_id' => $product->id,
                        'price' => $variantData['price'],
                        'stock' => $variantData['stock'],
                        'metadata' => $variantData['metadata'],
                        'is_default' => $variantData['is_default'],
                        'is_active' => true,
                    ]
                );

                $indexedVariants[$variant->sku] = $variant;
            }
        }

        return $indexedVariants;
    }

    /**
     * @return array{free_shipping_promo: Promotion, coupon_promo: Promotion, affiliate: Affiliate, coupon: Coupon}
     */
    private function upsertMarketingAndAffiliates(Tenant $tenant): array
    {
        $freeShippingPromo = Promotion::query()->updateOrCreate(
            ['tenant_id' => $tenant->id, 'name' => 'Envío Gratis Compras +L2,500'],
            [
                'type' => 'AUTOMATIC',
                'discount_type' => 'FREE_SHIPPING',
                'discount_value' => 0,
                'conditions' => ['min_subtotal' => 2500],
                'allow_stacking' => true,
                'starts_at' => now()->subDays(5),
                'ends_at' => null,
                'is_active' => true,
            ]
        );

        Promotion::query()->updateOrCreate(
            ['tenant_id' => $tenant->id, 'name' => 'Bono Técnico L150 en Compras +L3,200'],
            [
                'type' => 'AUTOMATIC',
                'discount_type' => 'FIXED_AMOUNT',
                'discount_value' => 150,
                'conditions' => ['min_subtotal' => 3200],
                'allow_stacking' => true,
                'starts_at' => now()->subDays(5),
                'ends_at' => null,
                'is_active' => true,
            ]
        );

        $couponPromo = Promotion::query()->updateOrCreate(
            ['tenant_id' => $tenant->id, 'name' => 'Beneficio Comunidad Kinetic 10%'],
            [
                'type' => 'COUPON',
                'discount_type' => 'PERCENTAGE',
                'discount_value' => 10,
                'conditions' => ['min_subtotal' => 500],
                'allow_stacking' => true,
                'starts_at' => now()->subDays(5),
                'ends_at' => null,
                'is_active' => true,
            ]
        );

        $vipPromo = Promotion::query()->updateOrCreate(
            ['tenant_id' => $tenant->id, 'name' => 'Descuento Lanzamiento VIP L250'],
            [
                'type' => 'COUPON',
                'discount_type' => 'FIXED_AMOUNT',
                'discount_value' => 250,
                'conditions' => ['min_subtotal' => 1800],
                'allow_stacking' => false,
                'starts_at' => now()->subDays(5),
                'ends_at' => null,
                'is_active' => true,
            ]
        );

        $affiliate = Affiliate::query()->updateOrCreate(
            ['tenant_id' => $tenant->id, 'email' => 'carlos@runclub.hn'],
            [
                'name' => 'Carlos Rivera · TGU Run Club',
                'phone' => '+50498112233',
                'commission_type' => 'PERCENTAGE',
                'commission_value' => 8,
                'payment_info' => ['bank' => 'BAC Credomatic', 'account' => '720019283'],
                'is_active' => true,
            ]
        );

        $trailAffiliate = Affiliate::query()->updateOrCreate(
            ['tenant_id' => $tenant->id, 'email' => 'sofia@trailhn.com'],
            [
                'name' => 'Sofía Valladares · Trail Honduras',
                'phone' => '+50497445566',
                'commission_type' => 'FIXED_AMOUNT',
                'commission_value' => 120,
                'payment_info' => ['bank' => 'Banco Atlántida', 'account' => '1100984412'],
                'is_active' => true,
            ]
        );

        $coupon = Coupon::query()->updateOrCreate(
            ['tenant_id' => $tenant->id, 'code' => 'KINETIC10'],
            [
                'promotion_id' => $couponPromo->id,
                'affiliate_id' => $affiliate->id,
                'max_uses' => 500,
                'starts_at' => now()->subDays(5),
                'ends_at' => null,
                'is_active' => true,
            ]
        );

        Coupon::query()->updateOrCreate(
            ['tenant_id' => $tenant->id, 'code' => 'TRAILVIP'],
            [
                'promotion_id' => $vipPromo->id,
                'affiliate_id' => $trailAffiliate->id,
                'max_uses' => 200,
                'starts_at' => now()->subDays(5),
                'ends_at' => null,
                'is_active' => true,
            ]
        );

        return [
            'free_shipping_promo' => $freeShippingPromo,
            'coupon_promo' => $couponPromo,
            'affiliate' => $affiliate,
            'coupon' => $coupon,
        ];
    }

    /**
     * @param  array<string, ProductVariant>  $variants
     * @param  array{free_shipping_promo: Promotion, coupon_promo: Promotion, affiliate: Affiliate, coupon: Coupon}  $marketing
     */
    private function upsertSampleOrdersAndRewards(
        Tenant $tenant,
        array $variants,
        array $marketing,
        PartnerStore $rewardPartner
    ): void {
        $shellVariant = $variants['KW-SHL-OBS-M'];
        $teeVariant = $variants['KW-TEE-TIT-M'];

        $pendingOrder = Order::query()->updateOrCreate(
            ['tenant_id' => $tenant->id, 'order_number' => 'ORD-DEMO-KW01'],
            [
                'status' => 'PENDIENTE_PAGO',
                'customer_info' => [
                    'full_name' => 'Andrea Mejía',
                    'whatsapp_phone' => '+50499112233',
                    'email' => 'andrea.mejia@example.com',
                    'notes' => 'Llamar al llegar a recepción de Torre Metrópolis.',
                ],
                'shipping_address' => [
                    'zone_code' => 'TGU',
                    'zone_name' => 'Tegucigalpa — Entrega Express Mismo Día',
                    'address_line' => 'Torre Metrópolis, Piso 8, Blvd. Suyapa',
                    'reference' => 'Frente a Emisoras Unidas',
                ],
                'subtotal' => 1890.00,
                'auto_discount_total' => 0.00,
                'coupon_discount_total' => 189.00,
                'shipping_fee' => 85.00,
                'total_net' => 1786.00,
                'applied_promotion_id' => null,
                'applied_coupon_id' => $marketing['coupon']->id,
                'whatsapp_url' => 'https://wa.me/50499887766?text=Pedido%20ORD-DEMO-KW01',
                'completed_at' => null,
                'cancelled_at' => null,
            ]
        );

        OrderItem::query()->updateOrCreate(
            [
                'tenant_id' => $tenant->id,
                'order_id' => $pendingOrder->id,
                'variant_id' => $shellVariant->id,
            ],
            [
                'product_id' => $shellVariant->product_id,
                'quantity' => 1,
                'unit_price' => 1890.00,
                'line_total' => 1890.00,
                'variant_snapshot' => [
                    'sku' => $shellVariant->sku,
                    'product_name' => 'Chaqueta Kinetic Shell V2',
                    'attributes' => $shellVariant->metadata ?? [],
                    'unit_price' => 1890.00,
                ],
            ]
        );

        Commission::query()->updateOrCreate(
            ['tenant_id' => $tenant->id, 'order_id' => $pendingOrder->id],
            [
                'affiliate_id' => $marketing['affiliate']->id,
                'coupon_id' => $marketing['coupon']->id,
                'amount' => 151.20,
                'status' => 'RETENIDA',
                'paid_at' => null,
                'payment_reference' => null,
                'payment_receipt_url' => null,
            ]
        );

        $completedOrder = Order::query()->updateOrCreate(
            ['tenant_id' => $tenant->id, 'order_number' => 'ORD-DEMO-KW02'],
            [
                'status' => 'COMPLETADA',
                'customer_info' => [
                    'full_name' => 'Roberto Zelaya',
                    'whatsapp_phone' => '+50498776655',
                    'email' => 'roberto.zelaya@example.com',
                ],
                'shipping_address' => [
                    'zone_code' => 'TGU',
                    'zone_name' => 'Tegucigalpa — Entrega Express Mismo Día',
                    'address_line' => 'Col. Palmira, Av. República de Chile #412',
                ],
                'subtotal' => 2680.00,
                'auto_discount_total' => 85.00,
                'coupon_discount_total' => 268.00,
                'shipping_fee' => 85.00,
                'total_net' => 2412.00,
                'applied_promotion_id' => $marketing['free_shipping_promo']->id,
                'applied_coupon_id' => $marketing['coupon']->id,
                'whatsapp_url' => 'https://wa.me/50499887766?text=Pedido%20ORD-DEMO-KW02',
                'completed_at' => now()->subHours(3),
                'cancelled_at' => null,
            ]
        );

        OrderItem::query()->updateOrCreate(
            [
                'tenant_id' => $tenant->id,
                'order_id' => $completedOrder->id,
                'variant_id' => $shellVariant->id,
            ],
            [
                'product_id' => $shellVariant->product_id,
                'quantity' => 1,
                'unit_price' => 1890.00,
                'line_total' => 1890.00,
                'variant_snapshot' => [
                    'sku' => $shellVariant->sku,
                    'product_name' => 'Chaqueta Kinetic Shell V2',
                    'attributes' => $shellVariant->metadata ?? [],
                    'unit_price' => 1890.00,
                ],
            ]
        );

        OrderItem::query()->updateOrCreate(
            [
                'tenant_id' => $tenant->id,
                'order_id' => $completedOrder->id,
                'variant_id' => $teeVariant->id,
            ],
            [
                'product_id' => $teeVariant->product_id,
                'quantity' => 1,
                'unit_price' => 790.00,
                'line_total' => 790.00,
                'variant_snapshot' => [
                    'sku' => $teeVariant->sku,
                    'product_name' => 'Camiseta Aero Merino Pro',
                    'attributes' => $teeVariant->metadata ?? [],
                    'unit_price' => 790.00,
                ],
            ]
        );

        Commission::query()->updateOrCreate(
            ['tenant_id' => $tenant->id, 'order_id' => $completedOrder->id],
            [
                'affiliate_id' => $marketing['affiliate']->id,
                'coupon_id' => $marketing['coupon']->id,
                'amount' => 214.40,
                'status' => 'APROBADA',
                'paid_at' => null,
                'payment_reference' => null,
                'payment_receipt_url' => null,
            ]
        );

        OutboundReward::query()->updateOrCreate(
            ['tenant_id' => $tenant->id, 'code' => 'RWD-KINE-CAFE-2026'],
            [
                'order_id' => $completedOrder->id,
                'partner_store_id' => $rewardPartner->id,
                'status' => 'EMITIDO',
                'expires_at' => now()->addDays(30),
                'redeemed_at' => null,
                'email_sent_at' => now()->subHours(3),
            ]
        );
    }
}
