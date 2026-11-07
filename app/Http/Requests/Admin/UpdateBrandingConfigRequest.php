<?php

namespace App\Http\Requests\Admin;

use App\Context\TenantContext;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateBrandingConfigRequest extends FormRequest
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
            'primary_color' => ['sometimes', 'nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'secondary_color' => ['sometimes', 'nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'accent_color' => ['sometimes', 'nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'font_family' => ['sometimes', 'nullable', 'string', 'max:100'],
            'logo_url' => ['sometimes', 'nullable', 'string', 'url', 'starts_with:https://', 'max:500'],
            'banner_url' => ['sometimes', 'nullable', 'string', 'url', 'starts_with:https://', 'max:500'],
            'favicon_url' => ['sometimes', 'nullable', 'string', 'url', 'starts_with:https://', 'max:500'],
            'social_links' => ['sometimes', 'nullable', 'array'],
            'social_links.whatsapp' => ['sometimes', 'nullable', 'string', 'max:50'],
            'social_links.instagram' => ['sometimes', 'nullable', 'string', 'max:100'],
            'social_links.facebook' => ['sometimes', 'nullable', 'string', 'max:100'],
            'social_links.tiktok' => ['sometimes', 'nullable', 'string', 'max:100'],
        ];
    }
}
