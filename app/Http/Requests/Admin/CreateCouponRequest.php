<?php

namespace App\Http\Requests\Admin;

use App\Context\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateCouponRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(TenantContext $tenantContext): array
    {
        $tenantId = $tenantContext->id();

        return [
            'promotion_id' => [
                'required',
                'uuid',
                Rule::exists('promotions', 'id')->where('tenant_id', $tenantId),
            ],
            'affiliate_id' => [
                'nullable',
                'uuid',
                Rule::exists('affiliates', 'id')->where('tenant_id', $tenantId),
            ],
            'code' => ['required', 'string', 'max:50'],
            'max_uses' => ['nullable', 'integer', 'min:1'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
