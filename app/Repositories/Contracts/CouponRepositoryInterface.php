<?php

namespace App\Repositories\Contracts;

use App\Models\Coupon;
use Illuminate\Database\Eloquent\Collection;

interface CouponRepositoryInterface
{
    public function findById(string $id): ?Coupon;

    public function findByIdWithLock(string $id): ?Coupon;

    public function findByCodeWithPromotion(string $code): ?Coupon;

    public function findByCodeWithLock(string $code): ?Coupon;

    public function incrementUsage(Coupon $coupon): void;

    public function decrementUsage(Coupon $coupon): void;

    public function existsByCode(string $code, ?string $ignoreId = null): bool;

    /**
     * @return Collection<int, Coupon>
     */
    public function listForAdmin(): Collection;

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Coupon;
}
