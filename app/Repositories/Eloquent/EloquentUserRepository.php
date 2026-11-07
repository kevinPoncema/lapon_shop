<?php

namespace App\Repositories\Eloquent;

use App\Context\TenantContext;
use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;

class EloquentUserRepository implements UserRepositoryInterface
{
    public function __construct(
        protected TenantContext $tenantContext
    ) {}

    public function findByEmail(string $email): ?User
    {
        return User::query()
            ->whereRaw('LOWER(email) = ?', [strtolower(trim($email))])
            ->first();
    }

    public function findById(string $id): ?User
    {
        return User::query()->find($id);
    }
}
