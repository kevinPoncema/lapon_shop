<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class CreatePromotionRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:150'],
            'type' => ['required', 'string', 'in:AUTOMATIC,COUPON'],
            'discount_type' => ['required', 'string', 'in:PERCENTAGE,FIXED_AMOUNT,FREE_SHIPPING'],
            'discount_value' => ['required', 'numeric', 'min:0'],
            'conditions' => ['nullable', 'array'],
            'allow_stacking' => ['sometimes', 'boolean'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
