<?php

namespace App\Http\Requests\Admin;

use App\Context\TenantContext;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateShippingZonesRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(TenantContext $tenantContext): bool
    {
        $user = $this->user();

        return $user !== null
            && $tenantContext->isSet()
            && $user->tenant_id === $tenantContext->id()
            && (bool) $user->is_active;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'shipping_zones' => ['required', 'array'],
            'shipping_zones.*.code' => ['required', 'string', 'max:20', 'distinct:ignore_case'],
            'shipping_zones.*.name' => ['required', 'string', 'max:150'],
            'shipping_zones.*.fee' => ['required', 'numeric', 'min:0'],
        ];
    }
}
