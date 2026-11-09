<?php

namespace App\Http\Requests;

use App\Enums\JobSourceDriver;
use App\Models\JobSource;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class JobSourceRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var JobSource|null $source */
        $source = $this->route('source');
        $driver = JobSourceDriver::tryFrom((string) $this->input('driver'));

        return [
            'name' => ['required', 'string', 'max:100'],
            'driver' => ['required', Rule::enum(JobSourceDriver::class)],
            'identifier' => [
                Rule::requiredIf($driver?->requiresIdentifier() ?? true),
                'nullable',
                'string',
                'max:100',
                'regex:'.($driver ?? JobSourceDriver::Greenhouse)->identifierPattern(),
                Rule::unique('job_sources')->where('driver', $this->input('driver'))->ignore($source?->id),
            ],
            'title_filter' => ['nullable', 'string', 'max:500'],
            'fetch_interval_minutes' => ['required', 'integer', 'min:60', 'max:10080'],
            'is_enabled' => ['boolean'],
            'compliance_notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'identifier.required' => 'This source type needs the company name from its job board URL.',
            'identifier.unique' => 'This source has already been added.',
            'identifier.regex' => 'That doesn\'t look like a valid value for this source type.',
        ];
    }
}
