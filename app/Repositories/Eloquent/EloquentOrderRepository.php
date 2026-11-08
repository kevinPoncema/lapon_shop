<?php

namespace App\Repositories\Eloquent;

use App\Context\TenantContext;
use App\Models\Order;
use App\Repositories\Contracts\OrderRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

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

    public function findDetailedById(string $id): ?Order
    {
        return $this->findDetailForAdmin($id);
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
                $dateTo = (string) $filters['date_to'];
                if (strlen($dateTo) === 10) {
                    $dateTo .= ' 23:59:59';
                }
                $query->where('created_at', '<=', $dateTo);
            })
            ->when(! empty($filters['q']) || ! empty($filters['search']), function ($query) use ($filters) {
                $rawSearch = ! empty($filters['q']) ? $filters['q'] : $filters['search'];
                $search = '%'.trim((string) $rawSearch).'%';
                $operator = DB::getDriverName() === 'pgsql' ? 'ILIKE' : 'LIKE';
                $query->where(function ($q) use ($search, $operator) {
                    $q->where('order_number', $operator, $search)
                        ->orWhere('customer_info->full_name', $operator, $search)
                        ->orWhere('customer_info->whatsapp_phone', $operator, $search)
                        ->orWhere('customer_info->email', $operator, $search);
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
