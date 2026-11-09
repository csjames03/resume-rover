<?php

namespace App\Http\Requests;

use App\Services\Resumes\ResumeTextExtractor;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreResumeRequest extends FormRequest
{
    private const MAX_KILOBYTES = 5120;

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'file' => [
                'required',
                'file',
                'mimetypes:'.ResumeTextExtractor::PDF.','.ResumeTextExtractor::DOCX,
                'max:'.self::MAX_KILOBYTES,
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.mimetypes' => 'Upload a PDF or Word (.docx) file.',
            'file.max' => 'The file must be 5 MB or smaller.',
        ];
    }
}
