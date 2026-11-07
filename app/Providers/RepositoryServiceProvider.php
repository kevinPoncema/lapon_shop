<?php

namespace App\Providers;

use App\Repositories\Contracts\TenantConfigRepositoryInterface;
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
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
