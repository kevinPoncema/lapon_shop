<?php

namespace App\Repositories\Eloquent;

use App\Context\TenantContext;
use App\Models\Commission;
use App\Repositories\Contracts\CommissionRepositoryInterface;

class EloquentCommissionRepository implements CommissionRepositoryInterface
{
    public function __construct(
        protected TenantContext $tenantContext
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Commission
    {
        $data['tenant_id'] = $this->tenantContext->id();

        return Commission::query()->create($data);
    }

    public function findByOrderId(string $orderId): ?Commission
    {
        return Commission::query()->where('order_id', $orderId)->first();
    }

    public function updateStatus(Commission $commission, string $status): Commission
    {
        $commission->update(['status' => $status]);

        return $commission->fresh();
    }
}
