<?php

namespace App\Models\Traits;

use App\Context\TenantContext;
use App\Models\Scopes\TenantScope;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Trait for models belonging to a specific tenant.
 *
 * Ensures automatic query isolation via TenantScope and enforces
 * immutable tenant_id assignment upon creation.
 *
 * @property string $tenant_id
 * @property-read Tenant $tenant
 */
trait BelongsToTenant
{
    /**
     * Boot the trait to attach global scopes and creation lifecycle checks.
     */
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(new TenantScope);

        static::creating(function (Model $model): void {
            $context = app(TenantContext::class);

            if ($context->isSet()) {
                $activeId = $context->id();
                $modelTenantId = $model->getAttribute('tenant_id');

                if (! empty($modelTenantId) && $modelTenantId !== $activeId) {
                    throw new LogicException(sprintf(
                        'Violación de seguridad Multi-Tenant: Intento de persistir [%s] con tenant_id [%s] en contexto del tenant [%s].',
                        static::class,
                        (string) $modelTenantId,
                        $activeId
                    ));
                }

                $model->setAttribute('tenant_id', $activeId);

                return;
            }

            if (empty($model->getAttribute('tenant_id'))) {
                throw new LogicException(sprintf(
                    'Violación de seguridad Multi-Tenant: No se puede crear [%s] sin un tenant_id explícito o un contexto activo.',
                    static::class
                ));
            }
        });
    }

    /**
     * Relationship with the owning tenant.
     *
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    /**
     * Scope a query to bypass the tenant scope explicitly (e.g. system maintenance).
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeWithoutTenantScope(Builder $query): Builder
    {
        return $query->withoutGlobalScope(TenantScope::class);
    }
}
