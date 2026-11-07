<?php

namespace App\Providers;

use App\Repositories\Contracts\AffiliateRepositoryInterface;
use App\Repositories\Contracts\CategoryRepositoryInterface;
use App\Repositories\Contracts\CommissionRepositoryInterface;
use App\Repositories\Contracts\CouponRepositoryInterface;
use App\Repositories\Contracts\OrderItemRepositoryInterface;
use App\Repositories\Contracts\OrderRepositoryInterface;
use App\Repositories\Contracts\OutboundRewardRepositoryInterface;
use App\Repositories\Contracts\PartnerStoreRepositoryInterface;
use App\Repositories\Contracts\ProductRepositoryInterface;
use App\Repositories\Contracts\ProductVariantRepositoryInterface;
use App\Repositories\Contracts\PromotionRepositoryInterface;
use App\Repositories\Contracts\TenantConfigRepositoryInterface;
use App\Repositories\Eloquent\EloquentAffiliateRepository;
use App\Repositories\Eloquent\EloquentCategoryRepository;
use App\Repositories\Eloquent\EloquentCommissionRepository;
use App\Repositories\Eloquent\EloquentCouponRepository;
use App\Repositories\Eloquent\EloquentOrderItemRepository;
use App\Repositories\Eloquent\EloquentOrderRepository;
use App\Repositories\Eloquent\EloquentOutboundRewardRepository;
use App\Repositories\Eloquent\EloquentPartnerStoreRepository;
use App\Repositories\Eloquent\EloquentProductRepository;
use App\Repositories\Eloquent\EloquentProductVariantRepository;
use App\Repositories\Eloquent\EloquentPromotionRepository;
use App\Repositories\Eloquent\EloquentTenantConfigRepository;
use Illuminate\Support\ServiceProvider;

class RepositoryServiceProvider extends ServiceProvider
{
    /**
     * All of the container bindings that should be registered.
     *
     * @var array<class-string, class-string>
     */
    public array $bindings = [
        TenantConfigRepositoryInterface::class => EloquentTenantConfigRepository::class,
        CategoryRepositoryInterface::class => EloquentCategoryRepository::class,
        ProductRepositoryInterface::class => EloquentProductRepository::class,
        ProductVariantRepositoryInterface::class => EloquentProductVariantRepository::class,
        PromotionRepositoryInterface::class => EloquentPromotionRepository::class,
        CouponRepositoryInterface::class => EloquentCouponRepository::class,
        AffiliateRepositoryInterface::class => EloquentAffiliateRepository::class,
        OrderRepositoryInterface::class => EloquentOrderRepository::class,
        OrderItemRepositoryInterface::class => EloquentOrderItemRepository::class,
        CommissionRepositoryInterface::class => EloquentCommissionRepository::class,
        OutboundRewardRepositoryInterface::class => EloquentOutboundRewardRepository::class,
        PartnerStoreRepositoryInterface::class => EloquentPartnerStoreRepository::class,
    ];

    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(
            TenantConfigRepositoryInterface::class,
            EloquentTenantConfigRepository::class
        );
        $this->app->bind(
            CategoryRepositoryInterface::class,
            EloquentCategoryRepository::class
        );
        $this->app->bind(
            ProductRepositoryInterface::class,
            EloquentProductRepository::class
        );
        $this->app->bind(
            ProductVariantRepositoryInterface::class,
            EloquentProductVariantRepository::class
        );
        $this->app->bind(
            PromotionRepositoryInterface::class,
            EloquentPromotionRepository::class
        );
        $this->app->bind(
            CouponRepositoryInterface::class,
            EloquentCouponRepository::class
        );
        $this->app->bind(
            AffiliateRepositoryInterface::class,
            EloquentAffiliateRepository::class
        );
        $this->app->bind(
            OrderRepositoryInterface::class,
            EloquentOrderRepository::class
        );
        $this->app->bind(
            OrderItemRepositoryInterface::class,
            EloquentOrderItemRepository::class
        );
        $this->app->bind(
            CommissionRepositoryInterface::class,
            EloquentCommissionRepository::class
        );
        $this->app->bind(
            OutboundRewardRepositoryInterface::class,
            EloquentOutboundRewardRepository::class
        );
        $this->app->bind(
            PartnerStoreRepositoryInterface::class,
            EloquentPartnerStoreRepository::class
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
