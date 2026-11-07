<?php

namespace App\Repositories\Contracts;

use App\Models\Order;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface OrderRepositoryInterface
{
    public function findById(string $id): ?Order;

    public function findByIdWithLock(string $id): ?Order;

    public function findDetailForAdmin(string $id): ?Order;

    public function findDetailedById(string $id): ?Order;

    public function existsByOrderNumber(string $orderNumber): bool;

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Order>
     */
    public function paginateForAdmin(array $filters, int $perPage = 20): LengthAwarePaginator;

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Order;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function updateStatus(Order $order, array $attributes): Order;
}
