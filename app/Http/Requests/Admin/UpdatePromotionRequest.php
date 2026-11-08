<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePromotionRequest extends FormRequest
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
            'name' => ['sometimes', 'string', 'max:150'],
            'type' => ['sometimes', 'string', 'in:AUTOMATIC,COUPON'],
            'discount_type' => ['sometimes', 'string', 'in:PERCENTAGE,FIXED_AMOUNT,FREE_SHIPPING'],
            'discount_value' => ['sometimes', 'numeric', 'min:0'],
            'conditions' => ['nullable', 'array'],
            'allow_stacking' => ['sometimes', 'boolean'],
            'starts_at' => ['sometimes', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
