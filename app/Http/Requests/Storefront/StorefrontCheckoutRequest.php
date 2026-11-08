<?php

namespace App\Http\Requests\Storefront;

use App\Context\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorefrontCheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $idempotencyKey = $this->header('Idempotency-Key');
        if ($idempotencyKey !== null) {
            $this->merge(['idempotency_key' => $idempotencyKey]);
        }

        if ($this->has('coupon_code') && is_string($this->input('coupon_code'))) {
            $this->merge(['coupon_code' => strtoupper(trim((string) $this->input('coupon_code')))]);
        }

        $shippingAddress = $this->input('shipping_address');
        if (is_array($shippingAddress) && isset($shippingAddress['region_code']) && is_string($shippingAddress['region_code'])) {
            $shippingAddress['region_code'] = strtoupper(trim($shippingAddress['region_code']));
            $this->merge(['shipping_address' => $shippingAddress]);
        }
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(TenantContext $tenantContext): array
    {
        $tenantId = $tenantContext->id();

        return [
            'idempotency_key' => ['required', 'uuid'],
            'customer_info' => ['required', 'array'],
            'customer_info.full_name' => ['required', 'string', 'max:150'],
            'customer_info.whatsapp_phone' => ['required', 'string', 'max:30'],
            'customer_info.email' => ['required', 'email', 'max:150'],
            'shipping_address' => ['required', 'array'],
            'shipping_address.region_code' => ['required', 'string', 'max:30'],
            'shipping_address.address_line' => ['required', 'string', 'max:255'],
            'shipping_address.reference' => ['nullable', 'string', 'max:255'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.variant_id' => [
                'required',
                'uuid',
                Rule::exists('product_variants', 'id')->where('tenant_id', $tenantId)->whereNull('deleted_at'),
            ],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'coupon_code' => ['nullable', 'string', 'max:50'],
        ];
    }
}
