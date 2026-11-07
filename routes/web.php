<?php

use App\Http\Controllers\Web\PortalShellController;
use Illuminate\Support\Facades\Route;

Route::get('/', [PortalShellController::class, 'home'])->name('home');

Route::prefix('t/{tenantSlug}')
    ->where(['tenantSlug' => '[a-z0-9-]+'])
    ->name('tenant.')
    ->group(function () {
        Route::get('/', [PortalShellController::class, 'storefrontCatalog'])->name('storefront.catalog');
        Route::get('/p/{productSlug}', [PortalShellController::class, 'storefrontProduct'])->name('storefront.product');
        Route::get('/checkout', [PortalShellController::class, 'storefrontCheckout'])->name('storefront.checkout');

        Route::prefix('admin')->name('admin.')->group(function () {
            Route::get('/login', [PortalShellController::class, 'adminLogin'])->name('login');
            Route::get('/', [PortalShellController::class, 'adminOrders'])->name('orders');
            Route::get('/catalog', [PortalShellController::class, 'adminCatalog'])->name('catalog');
            Route::get('/schema', [PortalShellController::class, 'adminSchema'])->name('schema');
            Route::get('/marketing', [PortalShellController::class, 'adminMarketing'])->name('marketing');
            Route::get('/affiliates', [PortalShellController::class, 'adminAffiliates'])->name('affiliates');
            Route::get('/partners', [PortalShellController::class, 'adminPartners'])->name('partners');
        });

        Route::prefix('partner-portal')->name('partner.')->group(function () {
            Route::get('/login', [PortalShellController::class, 'partnerLogin'])->name('login');
            Route::get('/', [PortalShellController::class, 'partnerPos'])->name('pos');
        });
    });

Route::get('/p/{productSlug}', [PortalShellController::class, 'subdomainStorefrontProduct'])->name('subdomain.storefront.product');
Route::get('/checkout', [PortalShellController::class, 'subdomainStorefrontCheckout'])->name('subdomain.storefront.checkout');

Route::prefix('admin')->name('subdomain.admin.')->group(function () {
    Route::get('/login', [PortalShellController::class, 'subdomainAdminLogin'])->name('login');
    Route::get('/', [PortalShellController::class, 'subdomainAdminOrders'])->name('orders');
    Route::get('/catalog', [PortalShellController::class, 'subdomainAdminCatalog'])->name('catalog');
    Route::get('/schema', [PortalShellController::class, 'subdomainAdminSchema'])->name('schema');
    Route::get('/marketing', [PortalShellController::class, 'subdomainAdminMarketing'])->name('marketing');
    Route::get('/affiliates', [PortalShellController::class, 'subdomainAdminAffiliates'])->name('affiliates');
    Route::get('/partners', [PortalShellController::class, 'subdomainAdminPartners'])->name('partners');
});

Route::prefix('partner-portal')->name('subdomain.partner.')->group(function () {
    Route::get('/login', [PortalShellController::class, 'subdomainPartnerLogin'])->name('login');
    Route::get('/', [PortalShellController::class, 'subdomainPartnerPos'])->name('pos');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');
});

require __DIR__.'/settings.php';
