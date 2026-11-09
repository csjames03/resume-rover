<?php

namespace App\Services\Resumes;

use App\Models\Resume;
use App\Models\User;
use Closure;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Where uploaded résumé files live. Locally that's the private disk; on hosts
 * whose disk is wiped on restart (Render's free plan) it's an S3-compatible
 * bucket, chosen with RESUME_DISK. Nothing outside this class names the disk.
 */
class ResumeFiles
{
    public function store(User $user, UploadedFile $file): string
    {
        $path = $file->store("resumes/{$user->id}", $this->diskName());

        if ($path === false) {
            throw new RuntimeException('The résumé file could not be stored.');
        }

        return $path;
    }

    public function exists(Resume $resume): bool
    {
        return $this->disk()->exists($resume->storage_path);
    }

    public function contents(Resume $resume): string
    {
        $contents = $this->disk()->get($resume->storage_path);

        if ($contents === null) {
            throw new RuntimeException('The résumé file is missing.');
        }

        return $contents;
    }

    public function delete(Resume $resume): void
    {
        $this->disk()->delete($resume->storage_path);
    }

    /**
     * Runs $work with a path on this machine (pdftotext needs a real file).
     * Remote files are copied to a temp file that's removed afterwards.
     *
     * @template T
     *
     * @param  Closure(string): T  $work
     * @return T
     */
    public function withLocalPath(Resume $resume, Closure $work): mixed
    {
        if (config("filesystems.disks.{$this->diskName()}.driver") === 'local') {
            return $work($this->disk()->path($resume->storage_path));
        }

        $temp = tempnam(sys_get_temp_dir(), 'resume-');

        if ($temp === false) {
            throw new RuntimeException('No temp space to read the résumé.');
        }

        try {
            file_put_contents($temp, $this->contents($resume));

            return $work($temp);
        } finally {
            @unlink($temp);
        }
    }

    private function disk(): Filesystem
    {
        return Storage::disk($this->diskName());
    }

    private function diskName(): string
    {
        return (string) config('filesystems.resume_disk', 'local');
    }
}
