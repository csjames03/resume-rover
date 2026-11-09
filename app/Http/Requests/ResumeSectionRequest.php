<?php

namespace App\Http\Requests;

use App\Enums\SectionType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ResumeSectionRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(SectionType::class)],
            'title' => ['nullable', 'string', 'max:250'],
            'organization' => ['nullable', 'string', 'max:250'],
            'location' => ['nullable', 'string', 'max:250'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'is_current' => ['boolean'],
            'description' => ['nullable', 'string', 'max:5000'],
            'bullets' => ['array', 'max:40'],
            'bullets.*' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * Section columns, without the bullets.
     *
     * @return array<string, mixed>
     */
    public function sectionAttributes(): array
    {
        $attributes = $this->safe()->except('bullets');

        if ($attributes['is_current'] ?? false) {
            $attributes['end_date'] = null;
        }

        return $attributes;
    }

    /**
     * @return list<string>
     */
    public function bullets(): array
    {
        return array_values(array_map('strval', array_filter((array) $this->validated('bullets', []))));
    }
}
