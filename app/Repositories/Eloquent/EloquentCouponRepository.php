<?php

namespace App\Repositories\Eloquent;

use App\Context\TenantContext;
use App\Models\Coupon;
use App\Repositories\Contracts\CouponRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class EloquentCouponRepository implements CouponRepositoryInterface
{
    public function __construct(
        protected TenantContext $tenantContext
    ) {}

    public function findById(string $id): ?Coupon
    {
        return Coupon::query()->with(['promotion', 'affiliate'])->find($id);
    }

    public function findByCodeWithPromotion(string $code): ?Coupon
    {
        return Coupon::query()
            ->with(['promotion', 'affiliate'])
            ->whereRaw('UPPER(code) = ?', [strtoupper(trim($code))])
            ->first();
    }

    public function findByCodeWithLock(string $code): ?Coupon
    {
        return Coupon::query()
            ->with(['promotion', 'affiliate'])
            ->whereRaw('UPPER(code) = ?', [strtoupper(trim($code))])
            ->lockForUpdate()
            ->first();
    }

    public function incrementUsage(Coupon $coupon): void
    {
        $coupon->increment('used_count');
    }

    public function decrementUsage(Coupon $coupon): void
    {
        if ($coupon->used_count > 0) {
            $coupon->decrement('used_count');
        }
    }

    public function existsByCode(string $code, ?string $ignoreId = null): bool
    {
        return Coupon::query()
            ->whereRaw('UPPER(code) = ?', [strtoupper(trim($code))])
            ->when($ignoreId !== null, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->exists();
    }

    /**
     * @return Collection<int, Coupon>
     */
    public function listForAdmin(): Collection
    {
        return Coupon::query()
            ->with(['promotion', 'affiliate'])
            ->latest('created_at')
            ->get();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Coupon
    {
        $data['tenant_id'] = $this->tenantContext->id();
        $data['code'] = strtoupper(trim((string) $data['code']));

        return Coupon::query()->create($data);
    }
}
