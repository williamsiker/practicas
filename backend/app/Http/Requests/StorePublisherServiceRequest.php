<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePublisherServiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $payload = $this->all();

        $map = [
            'description' => 'short_description',
            'authType' => 'auth_type',
            'monthlyLimit' => 'monthly_limit',
            'documentationUrl' => 'documentation_url',
            'termsAccepted' => 'terms_accepted',
            'usage' => 'usage_count',
            'version.current' => 'version.version',
            'version.versionLabel' => 'version.version',
            'version.status' => 'version.status',
            'version.releaseDate' => 'version.release_date',
            'version.compatibility' => 'version.compatibility',
            'version.documentation' => 'version.documentation_url',
            'version.requestable' => 'version.is_requestable',
            'version.limitSuggestion' => 'version.limit_suggestion',
        ];

        foreach ($map as $from => $to) {
            data_set($payload, $to, data_get($payload, $to, data_get($payload, $from)));
        }

        if (isset($payload['tags']) && is_string($payload['tags'])) {
            $payload['tags'] = array_filter(array_map('trim', explode(',', $payload['tags'])));
        }

        if (isset($payload['labels']) && is_string($payload['labels'])) {
            $payload['labels'] = array_filter(array_map('trim', explode(',', $payload['labels'])));
        }

        if (isset($payload['terms_accepted'])) {
            $payload['terms_accepted'] = filter_var($payload['terms_accepted'], FILTER_VALIDATE_BOOL);
        }

        $this->replace($payload);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'short_description' => ['required', 'string', 'max:500'],
            'department' => ['nullable', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:255'],
            'usage_count' => ['nullable', 'integer', 'min:0'],
            'coverage' => ['nullable', 'string', 'max:255'],
            'url' => ['required', 'url', 'max:2048'],
            'type' => ['required', Rule::in(['api-rest', 'form-web', 'archivo-batch', 'proceso-manual'])],
            'status' => ['required', Rule::in(['borrador', 'revision', 'aprobado', 'rechazado'])],
            'auth_type' => ['required', Rule::in(['oauth2', 'sso', 'api_key', 'ninguna'])],
            'schedule' => ['required', Rule::in(['office', 'full'])],
            'monthly_limit' => ['nullable', 'integer', 'min:0'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'max:50'],
            'labels' => ['nullable', 'array'],
            'labels.*' => ['string', 'max:100'],
            'owner' => ['nullable', 'string', 'max:255'],
            'documentation_url' => ['nullable', 'url', 'max:2048'],
            'terms_accepted' => ['accepted'],
            'version' => ['required', 'array'],
            'version.version' => ['required', 'string', 'max:50'],
            'version.status' => ['nullable', Rule::in(['available', 'maintenance', 'deprecated', 'draft'])],
            'version.release_date' => ['nullable', 'date'],
            'version.compatibility' => ['nullable', 'string', 'max:255'],
            'version.documentation_url' => ['nullable', 'url', 'max:2048'],
            'version.is_requestable' => ['nullable', 'boolean'],
            'version.limit_suggestion' => ['nullable', 'integer', 'min:0'],
            'version.notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
