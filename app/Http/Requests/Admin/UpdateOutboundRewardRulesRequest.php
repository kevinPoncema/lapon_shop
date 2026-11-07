<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateOutboundRewardRulesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'is_enabled' => ['required', 'boolean'],
            'partner_store_id' => ['required_if:is_enabled,true', 'nullable', 'string', 'uuid'],
            'min_order_amount' => ['required_if:is_enabled,true', 'nullable', 'numeric', 'min:0'],
            'validity_days' => ['required_if:is_enabled,true', 'nullable', 'integer', 'min:1'],
        ];
    }
}
