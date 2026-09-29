<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Auth\Actions\LoginUserAction;
use App\Domains\Auth\Actions\RegisterUserAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\ForgotPasswordRequest;
use App\Http\Requests\Api\V1\Auth\LoginRequest;
use App\Http\Requests\Api\V1\Auth\RegisterRequest;
use App\Http\Requests\Api\V1\Auth\ResetPasswordRequest;
use App\Http\Requests\Api\V1\Auth\UpdateProfileRequest;
use App\Http\Resources\Api\V1\UserResource;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function register(RegisterRequest $request, RegisterUserAction $register): JsonResponse
    {
        $result = $register->handle($request->validated());

        return response()->json(['data' => [
            'user' => (new UserResource($result['user']->load('claimedPlayer')))->resolve($request),
            'token' => $result['token'],
            'tokenType' => 'Bearer',
        ], 'meta' => ['apiVersion' => 'v1']], 201);
    }

    public function login(LoginRequest $request, LoginUserAction $login): JsonResponse
    {
        $result = $login->handle($request->string('email'), $request->string('password'), $request->string('deviceName'));
        if ($result === null) {
            return response()->json(['error' => [
                'code' => 'invalid_credentials', 'message' => 'The supplied credentials are invalid.',
                'requestId' => $request->attributes->get('request_id'), 'fieldErrors' => null, 'conflict' => null,
            ]], 401);
        }

        return response()->json(['data' => [
            'user' => (new UserResource($result['user']->load('claimedPlayer')))->resolve($request),
            'token' => $result['token'], 'tokenType' => 'Bearer',
        ], 'meta' => ['apiVersion' => 'v1']]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(['data' => (new UserResource($request->user()->load('claimedPlayer')))->resolve($request), 'meta' => ['apiVersion' => 'v1']]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json(['data' => ['revoked' => true], 'meta' => ['apiVersion' => 'v1']]);
    }

    public function updateProfile(UpdateProfileRequest $request): JsonResponse
    {
        $data = $request->validated();
        $user = $request->user();
        $user->update([
            'city' => $data['city'], 'playing_role' => $data['playingRole'],
            'batting_style' => $data['battingStyle'] ?? null, 'bowling_style' => $data['bowlingStyle'] ?? null,
            'bio' => $data['bio'] ?? null, 'photo_url' => $data['photoUrl'] ?? null,
        ]);

        return response()->json(['data' => (new UserResource($user->refresh()->load('claimedPlayer')))->resolve($request), 'meta' => ['apiVersion' => 'v1']]);
    }

    public function logoutAll(Request $request): JsonResponse
    {
        $request->user()->tokens()->delete();

        return response()->json(['data' => ['revoked' => true], 'meta' => ['apiVersion' => 'v1']]);
    }

    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        Password::sendResetLink(['email' => mb_strtolower($request->string('email'))]);

        return response()->json(['data' => ['message' => 'If the account exists, password reset instructions have been sent.'], 'meta' => ['apiVersion' => 'v1']]);
    }

    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $status = Password::reset($request->safe()->only(['email', 'password', 'password_confirmation', 'token']), function (User $user, string $password): void {
            $user->forceFill(['password' => Hash::make($password), 'remember_token' => Str::random(60)])->save();
            $user->tokens()->delete();
            event(new PasswordReset($user));
        });

        if ($status !== Password::PASSWORD_RESET) {
            return response()->json(['error' => ['code' => 'invalid_reset_token', 'message' => 'The reset token is invalid or expired.', 'requestId' => $request->attributes->get('request_id'), 'fieldErrors' => null, 'conflict' => null]], 422);
        }

        return response()->json(['data' => ['reset' => true], 'meta' => ['apiVersion' => 'v1']]);
    }
}
