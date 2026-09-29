<?php

namespace App\Domains\Auth\Actions;

use App\Models\User;
use Illuminate\Support\Facades\Hash;

class LoginUserAction
{
    /** @return array{user:User,token:string}|null */
    public function handle(string $email, string $password, string $deviceName): ?array
    {
        $user = User::query()->where('email', mb_strtolower(trim($email)))->first();
        if ($user === null || ! Hash::check($password, $user->password)) {
            return null;
        }

        $expiresAt = now('UTC')->addMinutes((int) config('stumps.token_expiration_minutes'));

        return ['user' => $user, 'token' => $user->createToken($deviceName, ['*'], $expiresAt)->plainTextToken];
    }
}
