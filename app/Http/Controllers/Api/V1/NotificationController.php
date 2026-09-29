<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\NotificationPreference;
use App\Models\UserNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $items = UserNotification::query()
            ->where('user_id', $request->user()->id)
            ->latest('created_at')->cursorPaginate(30)->withQueryString();

        return response()->json([
            'data' => collect($items->items())->map(fn (UserNotification $item) => $this->notification($item))->values(),
            'meta' => ['apiVersion' => 'v1', 'nextCursor' => optional($items->nextCursor())->encode()],
        ]);
    }

    public function read(Request $request, UserNotification $notification): JsonResponse
    {
        abort_unless($notification->user_id === $request->user()->id, 404);
        if ($notification->read_at === null) {
            $notification->forceFill(['read_at' => now()])->save();
        }

        return response()->json(['data' => $this->notification($notification->refresh()), 'meta' => ['apiVersion' => 'v1']]);
    }

    public function preferences(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->preference($this->preferenceFor($request)), 'meta' => ['apiVersion' => 'v1']]);
    }

    public function updatePreferences(Request $request): JsonResponse
    {
        $data = $request->validate([
            'matchUpdates' => ['required', 'boolean'], 'tournamentUpdates' => ['required', 'boolean'],
            'teamUpdates' => ['required', 'boolean'], 'systemUpdates' => ['required', 'boolean'],
            'marketing' => ['required', 'boolean'],
        ]);
        $preference = NotificationPreference::query()->updateOrCreate(
            ['user_id' => $request->user()->id],
            [
                'match_updates' => $data['matchUpdates'], 'tournament_updates' => $data['tournamentUpdates'],
                'team_updates' => $data['teamUpdates'], 'system_updates' => $data['systemUpdates'],
                'marketing' => $data['marketing'],
            ],
        );

        return response()->json(['data' => $this->preference($preference), 'meta' => ['apiVersion' => 'v1']]);
    }

    private function preferenceFor(Request $request): NotificationPreference
    {
        return NotificationPreference::query()->firstOrCreate(['user_id' => $request->user()->id]);
    }

    private function notification(UserNotification $item): array
    {
        return [
            'id' => $item->id, 'category' => $item->category, 'title' => $item->title,
            'body' => $item->body, 'deepLink' => $item->deep_link, 'data' => $item->data,
            'readAtUtc' => optional($item->read_at)?->toISOString(), 'createdAtUtc' => $item->created_at->toISOString(),
        ];
    }

    private function preference(NotificationPreference $item): array
    {
        return [
            'matchUpdates' => $item->match_updates, 'tournamentUpdates' => $item->tournament_updates,
            'teamUpdates' => $item->team_updates, 'systemUpdates' => $item->system_updates,
            'marketing' => $item->marketing, 'updatedAtUtc' => $item->updated_at->toISOString(),
        ];
    }
}
