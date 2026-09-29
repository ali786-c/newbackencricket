<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tournaments', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('owner_user_id')->constrained('users')->restrictOnDelete();
            $table->string('name', 120);
            $table->string('normalized_name', 120)->index();
            $table->string('city', 100);
            $table->string('season', 40);
            $table->string('status', 24)->default('registration');
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->text('description')->nullable();
            $table->string('logo_path')->nullable();
            $table->unsignedBigInteger('rule_profile_version')->default(1);
            $table->unsignedSmallInteger('overs_per_innings');
            $table->unsignedTinyInteger('balls_per_over');
            $table->unsignedTinyInteger('players_per_side');
            $table->unsignedTinyInteger('wickets_per_innings');
            $table->string('ball_type', 16);
            $table->unsignedTinyInteger('points_for_win')->default(2);
            $table->unsignedTinyInteger('points_for_tie')->default(1);
            $table->unsignedTinyInteger('points_for_no_result')->default(1);
            $table->unsignedBigInteger('version')->default(1);
            $table->timestamps();
            $table->index(['owner_user_id', 'status']);
            $table->index(['status', 'starts_at', 'ends_at']);
        });
        Schema::create('tournament_collaborators', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('tournament_id')->constrained('tournaments')->cascadeOnDelete();
            $table->foreignUlid('user_id')->constrained('users')->restrictOnDelete();
            $table->string('role', 24);
            $table->timestamps();
            $table->unique(['tournament_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tournament_collaborators');
        Schema::dropIfExists('tournaments');
    }
};
