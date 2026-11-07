<?php

namespace App\Models\Scopes;

use App\Context\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * @implements Scope<Model>
 */
class TenantScope implements Scope
{
    public function __construct(
        protected ?TenantContext $tenantContext = null
    ) {
        $this->tenantContext ??= app(TenantContext::class);
    }

    /**
     * Apply the scope to a given Eloquent query builder.
     */
    public function apply(Builder $builder, Model $model): void
    {
        if ($this->tenantContext !== null && $this->tenantContext->isSet()) {
            $builder->where($model->qualifyColumn('tenant_id'), '=', $this->tenantContext->id());
        }
    }
}
