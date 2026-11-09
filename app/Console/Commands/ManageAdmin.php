<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

/**
 * The only way to become an admin. Email verification is off, so an
 * email-based allowlist could be claimed by whoever registers first.
 */
class ManageAdmin extends Command
{
    protected $signature = 'users:admin
        {email : The account to change}
        {--revoke : Remove admin rights instead}';

    protected $description = 'Grant or revoke admin rights (managing the shared job sources)';

    public function handle(): int
    {
        $email = mb_strtolower(trim((string) $this->argument('email')));
        $user = User::query()->whereRaw('lower(email) = ?', [$email])->first();

        if ($user === null) {
            $this->error("No account uses {$email}.");

            return self::FAILURE;
        }

        $grant = ! $this->option('revoke');
        $user->forceFill(['is_admin' => $grant])->save();

        $this->info($grant ? "{$user->email} is now an admin." : "{$user->email} is no longer an admin.");

        return self::SUCCESS;
    }
}
