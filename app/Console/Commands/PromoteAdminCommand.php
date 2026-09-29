<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class PromoteAdminCommand extends Command
{
    protected $signature = 'stumps:admin {email} {--revoke : Remove administrator access}';

    protected $description = 'Grant or revoke STUMPS administrator access for an existing user.';

    public function handle(): int
    {
        $user = User::where('email', mb_strtolower((string) $this->argument('email')))->first();
        if (! $user) {
            $this->error('User not found. Register the account first.');

            return self::FAILURE;
        }
        $user->forceFill(['is_admin' => ! $this->option('revoke')])->save();
        $this->info($this->option('revoke') ? 'Administrator access revoked.' : 'Administrator access granted.');

        return self::SUCCESS;
    }
}
