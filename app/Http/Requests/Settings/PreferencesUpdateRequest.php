<?php

namespace App\Http\Requests\Settings;

use App\Enums\RemotePreference;
use App\Enums\Seniority;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PreferencesUpdateRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'target_titles' => ['array', 'max:10'],
            'target_titles.*' => ['string', 'max:100'],
            'locations' => ['array', 'max:10'],
            'locations.*' => ['string', 'max:100'],
            'remote_preference' => ['required', Rule::enum(RemotePreference::class)],
            'seniority' => ['nullable', Rule::enum(Seniority::class)],
            'excluded_keywords' => ['array', 'max:20'],
            'excluded_keywords.*' => ['string', 'max:50'],
        ];
    }

    /**
     * Blank entries removed and whitespace trimmed.
     *
     * @return array<string, mixed>
     */
    public function preferences(): array
    {
        $clean = fn (string $key) => array_values(array_unique(array_filter(array_map('trim', (array) $this->validated($key, [])))));

        return [
            'target_titles' => $clean('target_titles'),
            'locations' => $clean('locations'),
            'remote_preference' => $this->validated('remote_preference'),
            'seniority' => $this->validated('seniority'),
            'excluded_keywords' => $clean('excluded_keywords'),
        ];
    }
}
