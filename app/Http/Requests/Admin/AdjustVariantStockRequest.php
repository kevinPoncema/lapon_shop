<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdjustVariantStockRequest extends FormRequest
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
        $mode = $this->input('mode');

        $quantityRules = ['required', 'integer'];
        if ($mode === 'set') {
            $quantityRules[] = 'min:0';
        }

        return [
            'mode' => ['required', 'string', Rule::in(['set', 'adjust'])],
            'quantity' => $quantityRules,
            'reason' => ['nullable', 'string', 'max:255'],
        ];
    }
}
