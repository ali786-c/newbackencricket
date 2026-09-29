<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\MediaUpload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class MediaUploadController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $items = MediaUpload::query()->where('user_id', $request->user()->id)->latest()->limit(100)->get();

        return response()->json(['data' => $items->map(fn (MediaUpload $item) => $this->resource($item)), 'meta' => ['apiVersion' => 'v1']]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'id' => ['required', 'ulid'], 'purpose' => ['required', 'in:profile_photo,team_logo,tournament_logo'],
            'ownerType' => ['required', 'in:user,team,tournament'], 'ownerId' => ['nullable', 'ulid'],
            'file' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);
        $expectedOwnerType = match ($data['purpose']) {
            'profile_photo' => 'user',
            'team_logo' => 'team',
            'tournament_logo' => 'tournament',
        };
        abort_unless($data['ownerType'] === $expectedOwnerType, 422, 'Upload purpose does not match owner type.');
        abort_unless($this->canManageOwner($request, $data['ownerType'], $data['ownerId'] ?? null), 403);
        $existing = MediaUpload::query()->whereKey($data['id'])->first();
        if ($existing !== null) {
            abort_unless($existing->user_id === $request->user()->id, 409);

            return response()->json(['data' => $this->resource($existing), 'meta' => ['apiVersion' => 'v1']]);
        }
        $file = $request->file('file');
        $upload = new MediaUpload([
            'user_id' => $request->user()->id, 'purpose' => $data['purpose'],
            'owner_type' => $data['ownerType'], 'owner_id' => $data['ownerId'] ?? null,
            'status' => 'uploading', 'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(), 'size_bytes' => $file->getSize(),
        ]);
        $upload->id = $data['id'];
        $upload->save();
        try {
            $path = $file->store("media/{$request->user()->id}", 'public');
            $upload->forceFill(['path' => $path, 'status' => 'completed', 'error_code' => null])->save();
            $this->attachToOwner($upload);
        } catch (\Throwable $error) {
            report($error);
            $upload->forceFill(['status' => 'failed', 'error_code' => 'storage_failed'])->save();
        }

        return response()->json(['data' => $this->resource($upload->refresh()), 'meta' => ['apiVersion' => 'v1']], 201);
    }

    public function cancel(Request $request, MediaUpload $mediaUpload): JsonResponse
    {
        abort_unless($mediaUpload->user_id === $request->user()->id, 404);
        if ($mediaUpload->path !== null) {
            Storage::disk($mediaUpload->disk)->delete($mediaUpload->path);
        }
        $mediaUpload->forceFill(['status' => 'cancelled', 'path' => null])->save();

        return response()->json(['data' => $this->resource($mediaUpload), 'meta' => ['apiVersion' => 'v1']]);
    }

    private function resource(MediaUpload $item): array
    {
        return [
            'id' => $item->id, 'purpose' => $item->purpose, 'ownerType' => $item->owner_type,
            'ownerId' => $item->owner_id, 'status' => $item->status,
            'url' => $item->path === null ? null : Storage::disk($item->disk)->url($item->path),
            'originalName' => $item->original_name, 'mimeType' => $item->mime_type,
            'sizeBytes' => $item->size_bytes, 'errorCode' => $item->error_code,
            'updatedAtUtc' => $item->updated_at->toISOString(),
        ];
    }

    private function canManageOwner(Request $request, string $ownerType, ?string $ownerId): bool
    {
        if ($ownerType === 'user') {
            return $ownerId === null || $ownerId === $request->user()->id;
        }
        if ($ownerId === null) {
            return false;
        }
        if ($ownerType === 'team') {
            return DB::table('teams')->where('id', $ownerId)->where('owner_user_id', $request->user()->id)->exists();
        }

        return DB::table('tournaments')->where('id', $ownerId)->where('owner_user_id', $request->user()->id)->exists()
            || DB::table('tournament_collaborators')->where('tournament_id', $ownerId)
                ->where('user_id', $request->user()->id)->exists();
    }

    private function attachToOwner(MediaUpload $upload): void
    {
        if ($upload->purpose === 'profile_photo') {
            DB::table('users')->where('id', $upload->user_id)->update([
                'photo_url' => Storage::disk($upload->disk)->url($upload->path),
                'updated_at' => now(),
            ]);
        } elseif ($upload->purpose === 'team_logo' && $upload->owner_id !== null) {
            DB::table('teams')->where('id', $upload->owner_id)->update([
                'logo_path' => $upload->path, 'updated_at' => now(),
            ]);
        } elseif ($upload->purpose === 'tournament_logo' && $upload->owner_id !== null) {
            DB::table('tournaments')->where('id', $upload->owner_id)->update([
                'logo_path' => $upload->path, 'updated_at' => now(),
            ]);
        }
    }
}
