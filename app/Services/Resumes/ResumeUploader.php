<?php

namespace App\Services\Resumes;

use App\Jobs\ParseResume;
use App\Models\Resume;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class ResumeUploader
{
    /** Private disk: résumés are never publicly reachable. */
    public const DISK = 'local';

    /**
     * Store the file, make it the active résumé, and queue parsing.
     * Re-uploading the identical active file is a no-op.
     */
    public function upload(User $user, UploadedFile $file): Resume
    {
        $hash = (string) hash_file('sha256', $file->getRealPath());
        $active = $user->activeResume;

        if ($active !== null && $active->file_hash === $hash) {
            return $active;
        }

        $path = $file->store("resumes/{$user->id}", self::DISK);

        $resume = DB::transaction(function () use ($user, $file, $hash, $path) {
            $user->resumes()->where('is_active', true)->update(['is_active' => false]);

            return $user->resumes()->create([
                'original_filename' => $file->getClientOriginalName(),
                'storage_path' => $path,
                'mime_type' => (string) $file->getMimeType(),
                'file_size' => (int) $file->getSize(),
                'file_hash' => $hash,
                'is_active' => true,
            ]);
        });

        ParseResume::dispatch($resume->id);

        return $resume;
    }
}
