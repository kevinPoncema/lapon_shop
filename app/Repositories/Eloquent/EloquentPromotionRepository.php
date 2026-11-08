<?php

namespace App\Repositories\Eloquent;

use App\Context\TenantContext;
use App\Models\Promotion;
use App\Repositories\Contracts\PromotionRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class EloquentPromotionRepository implements PromotionRepositoryInterface
{
    public function __construct(
        protected TenantContext $tenantContext
    ) {}

    public function findById(string $id): ?Promotion
    {
        return Promotion::query()->find($id);
    }

    /**
     * @return Collection<int, Promotion>
     */
    public function getActiveAutomaticForTenant(): Collection
    {
        return Promotion::query()
            ->where('type', 'AUTOMATIC')
            ->where('is_active', true)
            ->where('starts_at', '<=', now())
            ->where(function ($query) {
                $query->whereNull('ends_at')
                    ->orWhere('ends_at', '>=', now());
            })
            ->get();
    }

    /**
     * @return Collection<int, Promotion>
     */
    public function listForAdmin(): Collection
    {
        return Promotion::query()
            ->latest('created_at')
            ->get();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Promotion
    {
        $data['tenant_id'] = $this->tenantContext->id();

        return Promotion::query()->create($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Promotion $promotion, array $data): Promotion
    {
        $promotion->update($data);

        return $promotion->fresh();
    }
}
