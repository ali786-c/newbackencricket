<?php

use App\Http\Controllers\Api\V1\DuplicateCandidateController;
use App\Http\Controllers\Api\V1\EntityMergeRequestController;
use App\Http\Controllers\Api\V1\IdentityResolutionController;
use App\Http\Controllers\Api\V1\PlayerClaimRequestController;
use App\Http\Controllers\Api\V1\PlayerController;
use App\Http\Controllers\Api\V1\TeamController;
use App\Http\Controllers\Api\V1\TeamMembershipController;
use App\Http\Controllers\Api\V1\TeamOwnershipTransferController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/health', fn () => [
    'status' => 'ok',
    'service' => 'stumps-api',
]);

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::get('/players/{player}', [PlayerController::class, 'show'])->name('api.v1.players.show');
Route::get('/teams/{team}', [TeamController::class, 'show'])->name('api.v1.teams.show');
Route::get('/identity/resolve/{publicCode}', IdentityResolutionController::class)->name('api.v1.identity.resolve');

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('/identity/duplicate-candidates/{entityType}', DuplicateCandidateController::class)->name('api.v1.identity.duplicate-candidates');
    Route::post('/identity/merge-requests', [EntityMergeRequestController::class, 'store'])->name('api.v1.identity.merge-requests.store');
    Route::patch('/identity/merge-requests/{mergeRequest}', [EntityMergeRequestController::class, 'update'])->name('api.v1.identity.merge-requests.update');
    Route::get('/players', [PlayerController::class, 'index'])->name('api.v1.players.index');
    Route::post('/players', [PlayerController::class, 'store'])->name('api.v1.players.store');
    Route::patch('/players/{player}', [PlayerController::class, 'update'])->name('api.v1.players.update');
    Route::delete('/players/{player}', [PlayerController::class, 'destroy'])->name('api.v1.players.destroy');
    Route::post('/players/{player}/claim-requests', [PlayerClaimRequestController::class, 'store'])->name('api.v1.player-claims.store');
    Route::patch('/players/{player}/claim-requests/{claimRequest}', [PlayerClaimRequestController::class, 'update'])->name('api.v1.player-claims.update');
    Route::get('/teams', [TeamController::class, 'index'])->name('api.v1.teams.index');
    Route::post('/teams', [TeamController::class, 'store'])->name('api.v1.teams.store');
    Route::patch('/teams/{team}', [TeamController::class, 'update'])->name('api.v1.teams.update');
    Route::delete('/teams/{team}', [TeamController::class, 'destroy'])->name('api.v1.teams.destroy');
    Route::post('/teams/{team}/ownership-transfers', [TeamOwnershipTransferController::class, 'store'])->name('api.v1.team-ownership-transfers.store');
    Route::patch('/teams/{team}/ownership-transfers/{ownershipTransfer}', [TeamOwnershipTransferController::class, 'update'])->name('api.v1.team-ownership-transfers.update');
    Route::post('/teams/{team}/memberships', [TeamMembershipController::class, 'store'])->name('api.v1.team-memberships.store');
    Route::patch('/teams/{team}/memberships/{membership}', [TeamMembershipController::class, 'update'])->name('api.v1.team-memberships.update');
});
