<?php

namespace App\Repositories\Contracts;

use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Database\Eloquent\Collection;

interface OrderItemRepositoryInterface
{
    /**
     * @param  list<array<string, mixed>>  $itemsPayload
     * @return Collection<int, OrderItem>
     */
    public function createManyForOrder(Order $order, array $itemsPayload): Collection;
}
