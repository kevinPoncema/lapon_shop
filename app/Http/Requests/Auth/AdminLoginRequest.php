<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class AdminLoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('email')) {
            $this->merge([
                'email' => strtolower(trim((string) $this->input('email'))),
            ]);
        }
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'max:150'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:100'],
        ];
    }

    /**
     * @param  array-key|null  $key
     * @return ($key is null ? array{email: string, password: string, device_name?: string|null} : mixed)
     */
    public function validated($key = null, mixed $default = null): mixed
    {
        /** @var array{email: string, password: string, device_name?: string|null} $validated */
        $validated = parent::validated($key, $default);

        return $validated;
    }
}
