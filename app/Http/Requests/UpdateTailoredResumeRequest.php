<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The user edits wording only. Structure and evidence links stay as reviewed.
 */
class UpdateTailoredResumeRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'summary' => ['nullable', 'string', 'max:1500'],
            'sections' => ['array'],
            'sections.*.bullets' => ['array'],
            'sections.*.bullets.*' => ['nullable', 'string', 'max:600'],
        ];
    }
}
