<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Tenant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Telescope\Telescope;

class DemoCatalogStressSeeder extends Seeder
{
    /**
     * Total de productos a generar por defecto para pruebas de estrés y latencia.
     * Puede sobrescribirse con la variable de entorno CATALOG_STRESS_COUNT.
     */
    public const DEFAULT_PRODUCT_COUNT = 1500;

    /**
     * Tamaño de lote (chunk) para inserciones masivas de alto rendimiento.
     */
    private const CHUNK_SIZE = 250;

    /**
     * Ejecuta el seeder secundario de estrés en el tenant por defecto ('kinetic-wear').
     */
    public function run(?int $count = null, string $tenantSlug = 'kinetic-wear'): void
    {
        if (class_exists(Telescope::class)) {
            Telescope::stopRecording();
        }
        DB::connection()->disableQueryLog();

        $targetCount = $count ?? (int) config('app.catalog_stress_count', self::DEFAULT_PRODUCT_COUNT);

        $tenant = Tenant::query()->where('slug', $tenantSlug)->first();

        if (! $tenant) {
            $this->command?->warn("El inquilino '{$tenantSlug}' no existe. Ejecutando DemoTenantSeeder primero...");
            $this->call(DemoTenantSeeder::class);
            $tenant = Tenant::query()->where('slug', $tenantSlug)->firstOrFail();
        }

        $categoryIds = Category::query()
            ->where('tenant_id', $tenant->id)
            ->where('is_active', true)
            ->pluck('id')
            ->all();

        if (empty($categoryIds)) {
            $this->call(DemoTenantSeeder::class);
            $categoryIds = Category::query()
                ->where('tenant_id', $tenant->id)
                ->where('is_active', true)
                ->pluck('id')
                ->all();
        }

        $this->command?->info("Poblando catálogo de estrés con {$targetCount} productos para '{$tenant->name}'...");

        // Purga idempotente de productos generados previamente por este seeder de estrés
        $stressProductIds = Product::withTrashed()
            ->where('tenant_id', $tenant->id)
            ->where('slug', 'like', 'stress-%')
            ->pluck('id');

        if ($stressProductIds->isNotEmpty()) {
            foreach ($stressProductIds->chunk(500) as $chunkIds) {
                ProductVariant::withTrashed()->whereIn('product_id', $chunkIds)->forceDelete();
                Product::withTrashed()->whereIn('id', $chunkIds)->forceDelete();
            }
        }

        // Catálogos léxicos para generar nombres y atributos hiperrealistas
        $productTypes = [
            'Chaqueta Impermeable', 'Cortavientos Ultra-Tech', 'Pantalón Cargo Táctico',
            'Camiseta Térmica Merino', 'Hoodie Técnico Hidrófugo', 'Chaleco Acolchado Modular',
            'Shorts de Running Aerodinámicos', 'Leggings Compresión Pro', 'Parka de Expedición Polar',
            'Jersey Transpirable 3D', 'Mochila Técnica Rolltop', 'Botas de Montaña Trail-Grip',
            'Gorra Ligera Laser-Cut', 'Guantes Softshell Térmicos', 'Cuello Balaclava Multiuso',
        ];

        $techLines = [
            'Kinetic Matrix', 'Apex Storm', 'Stealth Shift', 'Vanguard DWR',
            'AeroMesh Pro', 'Titanium Shield', 'Nomad Expedition', 'CyberTech Elite',
            'HydroZero X', 'Merino Tech Ultra', 'Polartec Alpha', 'Cordura Ripstop',
        ];

        $editions = [
            'Gen 2', 'v3 Pro', 'All-Weather Edition', 'Stealth Edition', 'Ultralight',
            'Endurance Series', 'Tactical Spec', 'Thermal Lab', 'Modular Fit', 'Summit Series',
        ];

        $materials = [
            'Ripstop Nylon', 'Merino Tech', 'Cordura Lite', 'AeroMesh 3D',
            'Polartec Alpha', 'GORE-TEX Pro', 'Dyneema Composite',
        ];

        $fits = ['Athletic Fit', 'Relaxed Tech', 'Compression', 'Slim Tech'];
        $sizes = ['XS', 'S', 'M', 'L', 'XL'];
        $colors = ['Obsidian', 'Indigo', 'Titanium', 'Emerald', 'Sand', 'Graphite', 'Crimson'];

        $imagePool = [
            'https://images.unsplash.com/photo-1551028719-00167b16eac5?w=800&q=80',
            'https://images.unsplash.com/photo-1544441893-675973e31985?w=800&q=80',
            'https://images.unsplash.com/photo-1556905055-8f358a7a47b2?w=800&q=80',
            'https://images.unsplash.com/photo-1521572267360-ee0c2909d518?w=800&q=80',
            'https://images.unsplash.com/photo-1512436991641-6745cdb1723f?w=800&q=80',
            'https://images.unsplash.com/photo-1578587018452-892bacefd3f2?w=800&q=80',
            'https://images.unsplash.com/photo-1602810318383-e386cc2a3ccf?w=800&q=80',
            'https://images.unsplash.com/photo-1509551388413-e18d0ac5d495?w=800&q=80',
        ];

        $now = Carbon::now();
        $totalVariantsCount = 0;
        $productsBatch = [];
        $variantsBatch = [];

        for ($i = 1; $i <= $targetCount; $i++) {
            $productId = (string) Str::uuid();
            $typeName = $productTypes[($i - 1) % count($productTypes)];
            $lineName = $techLines[($i * 3) % count($techLines)];
            $editionName = $editions[($i * 7) % count($editions)];
            $code = str_pad((string) $i, 5, '0', STR_PAD_LEFT);

            $productName = "{$typeName} {$lineName} {$editionName} #{$code}";
            $slug = "stress-{$code}-".Str::slug("{$typeName}-{$lineName}");
            $categoryId = ! empty($categoryIds) ? $categoryIds[($i - 1) % count($categoryIds)] : null;

            // Precios escalonados entre 25.00 y 480.00 HNL / USD
            $basePrice = (float) number_format(25 + (($i * 37) % 455), 2, '.', '');
            $material = $materials[$i % count($materials)];
            $fit = $fits[$i % count($fits)];
            $impermeable = ($i % 3 === 0);
            $weight = 120 + (($i * 19) % 700);

            $metadata = [
                'material' => $material,
                'fit_corte' => $fit,
                'impermeable' => $impermeable,
                'peso_gramos' => $weight,
            ];

            // 1 a 3 imágenes de alta resolución
            $imgStart = $i % count($imagePool);
            $images = [
                $imagePool[$imgStart],
                $imagePool[($imgStart + 1) % count($imagePool)],
            ];

            // 96% de productos activos, 4% inactivos para probar filtros de estado
            $isActive = ($i % 25 !== 0);

            $productsBatch[] = [
                'id' => $productId,
                'tenant_id' => $tenant->id,
                'category_id' => $categoryId,
                'name' => $productName,
                'slug' => $slug,
                'description' => "Prenda técnica de alto rendimiento perteneciente a la colección {$lineName}. Diseñada con tejido {$material} de calce {$fit} para condiciones exigentes de uso urbano y outdoor.",
                'base_price' => $basePrice,
                'metadata' => json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                'images' => json_encode($images, JSON_UNESCAPED_SLASHES),
                'is_active' => $isActive,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ];

            // Generar entre 2 y 4 variantes por producto
            $variantCount = 2 + ($i % 3);
            for ($v = 0; $v < $variantCount; $v++) {
                $talla = $sizes[($i + $v) % count($sizes)];
                $color = $colors[($i + ($v * 2)) % count($colors)];
                $colorCode = strtoupper(substr($color, 0, 3));
                $sku = "KW-STR-{$code}-{$talla}-{$colorCode}";

                // 25% de variantes agotadas (stock = 0), resto con stock positivo
                $stock = ($i + $v) % 4 === 0 ? 0 : 5 + (($i * 7 + $v * 11) % 65);

                // Algunas variantes con sobreprecio de talla/color
                $priceOverride = ($v > 1 && $i % 2 === 0) ? ($basePrice + 15.00) : null;

                $variantMetadata = [
                    'talla' => $talla,
                    'color' => $color,
                ];

                $variantsBatch[] = [
                    'id' => (string) Str::uuid(),
                    'tenant_id' => $tenant->id,
                    'product_id' => $productId,
                    'sku' => $sku,
                    'price' => $priceOverride,
                    'stock' => $stock,
                    'metadata' => json_encode($variantMetadata, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                    'is_default' => ($v === 0),
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                    'deleted_at' => null,
                ];

                $totalVariantsCount++;
            }

            // Inserción en bloque para optimizar memoria y tiempo de ejecución
            if (count($productsBatch) >= self::CHUNK_SIZE) {
                DB::table('products')->insert($productsBatch);
                foreach (array_chunk($variantsBatch, 100) as $varChunk) {
                    DB::table('product_variants')->insert($varChunk);
                }
                $productsBatch = [];
                $variantsBatch = [];
            }
        }

        // Insertar el remanente
        if (! empty($productsBatch)) {
            DB::table('products')->insert($productsBatch);
            foreach (array_chunk($variantsBatch, 100) as $varChunk) {
                DB::table('product_variants')->insert($varChunk);
            }
        }

        Cache::flush();

        $this->command?->info("✓ Catálogo de estrés completado exitosamente: {$targetCount} productos y {$totalVariantsCount} variantes generadas.");
    }
}
