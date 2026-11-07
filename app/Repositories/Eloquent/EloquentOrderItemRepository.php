<?php

namespace App\Repositories\Eloquent;

use App\Context\TenantContext;
use App\Models\Order;
use App\Models\OrderItem;
use App\Repositories\Contracts\OrderItemRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class EloquentOrderItemRepository implements OrderItemRepositoryInterface
{
    public function __construct(
        protected TenantContext $tenantContext
    ) {}

    /**
     * @param  list<array<string, mixed>>  $itemsPayload
     * @return Collection<int, OrderItem>
     */
    public function createManyForOrder(Order $order, array $itemsPayload): Collection
    {
        $tenantId = $this->tenantContext->id();
        $created = new Collection;

        foreach ($itemsPayload as $itemData) {
            $itemData['tenant_id'] = $tenantId;
            $itemData['order_id'] = $order->id;
            $created->push(OrderItem::query()->create($itemData));
        }

        return $created;
    }
}
