<?php

namespace App\Http\Requests;

use App\Enums\ApplicationStatus;
use App\Enums\MatchCategory;
use App\Enums\RemoteType;
use App\Queries\JobPostingSearch;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * @phpstan-import-type Filters from JobPostingSearch
 */
class JobPostingIndexRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', Rule::enum(MatchCategory::class)],
            'remote' => ['nullable', Rule::enum(RemoteType::class)],
            'source' => ['nullable', 'integer', 'exists:job_sources,id'],
            'status' => ['nullable', Rule::in([...ApplicationStatus::values(), 'untracked'])],
            'include_closed' => ['nullable', 'boolean'],
            'sort' => ['nullable', Rule::in(JobPostingSearch::SORTS)],
        ];
    }

    /**
     * @return Filters
     */
    public function filters(): array
    {
        return [
            'q' => $this->validated('q'),
            'category' => $this->validated('category'),
            'remote' => $this->validated('remote'),
            'source' => $this->filled('source') ? (int) $this->validated('source') : null,
            'status' => $this->validated('status'),
            'include_closed' => $this->boolean('include_closed'),
            'sort' => $this->validated('sort') ?? 'score',
        ];
    }
}
