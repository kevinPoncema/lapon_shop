<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class CreatePartnerStoreRequest extends FormRequest
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
            'portal_email' => ['required', 'email', 'max:150'],
            'portal_password' => ['required', 'string', 'min:6'],
            'benefit_title' => ['required', 'string', 'max:255'],
            'terms_conditions' => ['nullable', 'string'],
            'contact_info' => ['nullable', 'array'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
