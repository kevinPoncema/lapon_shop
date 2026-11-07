<?php

namespace App\Repositories\Eloquent;

use App\Context\TenantContext;
use App\Models\Order;
use App\Repositories\Contracts\OrderRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class EloquentOrderRepository implements OrderRepositoryInterface
{
    public function __construct(
        protected TenantContext $tenantContext
    ) {}

    public function findById(string $id): ?Order
    {
        return Order::query()->find($id);
    }

    public function findByIdWithLock(string $id): ?Order
    {
        return Order::query()->where('id', $id)->lockForUpdate()->first();
    }

    public function findDetailForAdmin(string $id): ?Order
    {
        return Order::query()
            ->with([
                'items',
                'appliedPromotion',
                'appliedCoupon',
                'commission.affiliate',
                'outboundRewards.partnerStore',
            ])
            ->find($id);
    }

    public function existsByOrderNumber(string $orderNumber): bool
    {
        return Order::query()->where('order_number', $orderNumber)->exists();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Order>
     */
    public function paginateForAdmin(array $filters, int $perPage = 20): LengthAwarePaginator
    {
        return Order::query()
            ->with(['items', 'commission'])
            ->when(! empty($filters['status']), function ($query) use ($filters) {
                $query->where('status', (string) $filters['status']);
            })
            ->when(! empty($filters['date_from']), function ($query) use ($filters) {
                $query->where('created_at', '>=', $filters['date_from']);
            })
            ->when(! empty($filters['date_to']), function ($query) use ($filters) {
                $query->where('created_at', '<=', $filters['date_to']);
            })
            ->when(! empty($filters['search']), function ($query) use ($filters) {
                $search = '%'.trim((string) $filters['search']).'%';
                $query->where(function ($q) use ($search) {
                    $q->where('order_number', 'ILIKE', $search)
                        ->orWhere('customer_info->full_name', 'ILIKE', $search)
                        ->orWhere('customer_info->whatsapp_phone', 'ILIKE', $search)
                        ->orWhere('customer_info->email', 'ILIKE', $search);
                });
            })
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Order
    {
        $data['tenant_id'] = $this->tenantContext->id();

        return Order::query()->create($data);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function updateStatus(Order $order, array $attributes): Order
    {
        $order->update($attributes);

        return $order->fresh(['items', 'commission', 'outboundRewards']);
    }
}
