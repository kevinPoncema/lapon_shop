<?php

namespace App\Repositories\Contracts;

use App\Models\Commission;

interface CommissionRepositoryInterface
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Commission;

    public function findByOrderId(string $orderId): ?Commission;

    public function updateStatus(Commission $commission, string $status): Commission;
}
