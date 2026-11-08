<?php

namespace App\Providers;

use App\Context\TenantContext;
use App\Models\PersonalAccessToken;
use App\Repositories\Contracts\TenantConfigRepositoryInterface;
use App\Repositories\Eloquent\EloquentTenantConfigRepository;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Laravel\Sanctum\Sanctum;
use Symfony\Component\HttpFoundation\Response;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(TenantContext::class, fn () => new TenantContext);
        $this->app->bind(TenantConfigRepositoryInterface::class, EloquentTenantConfigRepository::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);
        $this->configureDefaults();
        $this->configureRateLimiting();
    }

    /**
     * Configure rate limiters for authentication endpoints.
     */
    protected function configureRateLimiting(): void
    {
        RateLimiter::for('tenant-auth', function (Request $request) {
            /** @var TenantContext $tenantContext */
            $tenantContext = app(TenantContext::class);
            $tenantKey = $tenantContext->isSet() ? $tenantContext->id() : 'global';

            $email = strtolower(trim((string) ($request->input('email') ?? $request->input('portal_email') ?? '')));
            $key = $tenantKey.'|'.$email.'|'.$request->ip();

            return Limit::perMinute(5)->by($key)->response(function () {
                return response()->json([
                    'error' => [
                        'code' => 'TOO_MANY_ATTEMPTS',
                        'message' => 'Demasiados intentos de autenticación. Por favor intente más tarde.',
                    ],
                ], Response::HTTP_TOO_MANY_REQUESTS);
            });
        });
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
