<?php

use App\Http\Controllers\Api\V1\Admin\AdminTenantConfigController;
use App\Http\Controllers\Api\V1\Storefront\StorefrontConfigController;
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
            });
    });
