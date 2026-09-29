<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\ApiControlState;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdminDashboardController extends Controller
{
    public function index(): View
    {
        return view('admin.dashboard', [
            'controls' => ApiControlState::current(),
            'metrics' => [
                'users' => DB::table('users')->count(),
                'matches' => DB::table('matches')->count(),
                'tournaments' => DB::table('tournaments')->count(),
                'pendingSync' => DB::table('sync_operations')->whereIn('status', ['pending', 'retryable_failure', 'syncing'])->count(),
                'conflicts' => DB::table('sync_operations')->where('status', 'conflict')->count(),
            ],
            'auditLogs' => DB::table('admin_audit_logs')->latest('created_at')->limit(10)->get(),
        ]);
    }

    public function updateControls(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'registration_enabled' => ['required', 'boolean'],
            'api_read_only' => ['required', 'boolean'],
            'rollout_stage' => ['required', Rule::in(['internal_alpha', 'closed_beta', 'tournament_beta', 'production'])],
        ]);
        DB::transaction(function () use ($data, $request): void {
            foreach ($data as $key => $value) {
                DB::table('system_settings')->updateOrInsert(['key' => $key], ['value' => is_bool($value) ? ($value ? '1' : '0') : $value, 'updated_at' => now(), 'created_at' => now()]);
            }
            DB::table('admin_audit_logs')->insert([
                'id' => (string) Str::ulid(), 'admin_user_id' => $request->user()->id,
                'action' => 'api_controls_updated', 'metadata' => json_encode($data, JSON_THROW_ON_ERROR),
                'ip_address' => $request->ip(), 'created_at' => now(),
            ]);
        });

        return back()->with('status', 'API controls updated and audited.');
    }
}
