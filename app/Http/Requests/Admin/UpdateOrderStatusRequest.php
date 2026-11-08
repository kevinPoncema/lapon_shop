<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateOrderStatusRequest extends FormRequest
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
            'status' => ['required', 'string', 'in:COMPLETADA,CANCELADA'],
            'payment_notes' => ['nullable', 'string', 'max:500'],
            'cancellation_reason' => ['nullable', 'string', 'max:500'],
        ];
    }
}
