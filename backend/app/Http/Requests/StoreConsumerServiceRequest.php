<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreConsumerServiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $payload = $this->all();

        $map = [
            'monthlyLimit' => 'monthly_limit',
            'customStart' => 'custom_start',
            'customEnd' => 'custom_end',
            'consumerName' => 'consumer_name',
            'consumerEmail' => 'consumer_email',
        ];

        foreach ($map as $from => $to) {
            if ($this->has($from)) {
                data_set($payload, $to, $this->input($from));
            }
        }

        $this->replace($payload);
    }

    public function rules(): array
    {
        return [
            'consumer_name' => ['nullable', 'string', 'max:255'],
            'consumer_email' => ['nullable', 'email', 'max:255'],
            'schedule' => ['required', Rule::in(['office', 'full', 'custom'])],
            'custom_start' => ['required_if:schedule,custom', 'nullable', 'date_format:H:i'],
            'custom_end' => ['required_if:schedule,custom', 'nullable', 'date_format:H:i', 'after:custom_start'],
            'monthly_limit' => ['required', 'integer', 'min:1'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
