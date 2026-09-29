<?php

namespace App\Domains\Auth\Actions;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class RegisterUserAction
{
    /** @param array{name:string,email:string,password:string,deviceName:string} $data
     * @return array{user:User,token:string}
     */
    public function handle(array $data): array
    {
        return DB::transaction(function () use ($data): array {
            $user = User::query()->create([
                'name' => trim($data['name']),
                'email' => mb_strtolower(trim($data['email'])),
                'password' => $data['password'],
            ]);

            $expiresAt = now('UTC')->addMinutes((int) config('stumps.token_expiration_minutes'));

            return ['user' => $user, 'token' => $user->createToken($data['deviceName'], ['*'], $expiresAt)->plainTextToken];
        });
    }
}
