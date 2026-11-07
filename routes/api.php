<?php

use App\Http\Controllers\Api\V1\Admin\AdminCategoryController;
use App\Http\Controllers\Api\V1\Admin\AdminProductController;
use App\Http\Controllers\Api\V1\Admin\AdminTenantConfigController;
use App\Http\Controllers\Api\V1\Admin\AdminVariantController;
use App\Http\Controllers\Api\V1\Storefront\StorefrontCategoryController;
use App\Http\Controllers\Api\V1\Storefront\StorefrontConfigController;
use App\Http\Controllers\Api\V1\Storefront\StorefrontProductController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::prefix('v1')
    ->middleware(['tenant.context'])
    ->group(function () {
        // Vitrina Pública (Storefront)
        Route::get('/storefront/config', StorefrontConfigController::class)
            ->name('api.v1.storefront.config');

        Route::get('/storefront/categories', StorefrontCategoryController::class)
            ->name('api.v1.storefront.categories.index');

        Route::get('/storefront/products', [StorefrontProductController::class, 'index'])
            ->name('api.v1.storefront.products.index');

        Route::get('/storefront/products/{slug}', [StorefrontProductController::class, 'show'])
            ->name('api.v1.storefront.products.show');

        // Panel Administrativo (Admin)
        Route::prefix('admin')
            ->middleware(['auth:sanctum', 'tenant.member'])
            ->group(function () {
                Route::get('/config', [AdminTenantConfigController::class, 'show'])
                    ->name('api.v1.admin.config.show');

                Route::put('/config/catalog-schema', [AdminTenantConfigController::class, 'updateCatalogSchema'])
                    ->name('api.v1.admin.config.catalog_schema.update');

                Route::put('/config/branding', [AdminTenantConfigController::class, 'updateBranding'])
                    ->name('api.v1.admin.config.branding.update');

                Route::put('/config/shipping-zones', [AdminTenantConfigController::class, 'updateShippingZones'])
                    ->name('api.v1.admin.config.shipping_zones.update');

                Route::get('/categories', [AdminCategoryController::class, 'index'])
                    ->name('api.v1.admin.categories.index');

                Route::post('/categories', [AdminCategoryController::class, 'store'])
                    ->name('api.v1.admin.categories.store');

                Route::get('/products', [AdminProductController::class, 'index'])
                    ->name('api.v1.admin.products.index');

                Route::post('/products', [AdminProductController::class, 'store'])
                    ->name('api.v1.admin.products.store');

                Route::get('/products/{id}', [AdminProductController::class, 'show'])
                    ->name('api.v1.admin.products.show');

                Route::put('/products/{id}', [AdminProductController::class, 'update'])
                    ->name('api.v1.admin.products.update');

                Route::patch('/variants/{id}/stock', [AdminVariantController::class, 'adjustStock'])
                    ->name('api.v1.admin.variants.stock.adjust');

                Route::patch('/variants/{id}/status', [AdminVariantController::class, 'updateStatus'])
                    ->name('api.v1.admin.variants.status.update');
            });
    });
