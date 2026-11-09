<?php

namespace App\Http\Requests;

use App\Enums\ApplicationStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateApplicationRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'status' => ['sometimes', Rule::enum(ApplicationStatus::class)],
            'notes' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'next_action' => ['sometimes', 'nullable', 'string', 'max:250'],
            'next_action_at' => ['sometimes', 'nullable', 'date'],
            'tailored_resume_id' => [
                'sometimes',
                'nullable',
                Rule::exists('tailored_resumes', 'id')->where('user_id', $this->user()?->id),
            ],
        ];
    }

    /**
     * @return array{status?: ApplicationStatus, notes?: string|null, next_action?: string|null, next_action_at?: string|null, tailored_resume_id?: int|null}
     */
    public function changes(): array
    {
        $changes = $this->validated();

        if (isset($changes['status'])) {
            $changes['status'] = ApplicationStatus::from($changes['status']);
        }

        return $changes;
    }
}
