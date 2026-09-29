<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tournament_teams', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('tournament_id')->constrained('tournaments')->cascadeOnDelete();
            $table->foreignUlid('team_id')->constrained('teams')->restrictOnDelete();
            $table->string('status', 24)->default('accepted');
            $table->unsignedSmallInteger('seed')->nullable();
            $table->timestamp('accepted_at');
            $table->timestamps();
            $table->unique(['tournament_id', 'team_id']);
        });
        Schema::create('tournament_squad_snapshots', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('tournament_team_id')->constrained('tournament_teams')->cascadeOnDelete();
            $table->unsignedBigInteger('version')->default(1);
            $table->json('roster');
            $table->timestamp('accepted_at');
            $table->timestamps();
            $table->unique(['tournament_team_id', 'version']);
        });
        Schema::create('tournament_fixtures', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('tournament_id')->constrained('tournaments')->cascadeOnDelete();
            $table->foreignUlid('home_tournament_team_id')->constrained('tournament_teams')->restrictOnDelete();
            $table->foreignUlid('away_tournament_team_id')->constrained('tournament_teams')->restrictOnDelete();
            $table->foreignUlid('match_id')->nullable()->constrained('matches')->restrictOnDelete();
            $table->string('stage', 40)->default('league');
            $table->string('group_name', 40)->nullable();
            $table->unsignedSmallInteger('round_number')->nullable();
            $table->timestamp('scheduled_at');
            $table->string('venue', 150);
            $table->string('status', 24)->default('scheduled');
            $table->unsignedBigInteger('rule_profile_version');
            $table->unsignedBigInteger('version')->default(1);
            $table->timestamps();
            $table->index(['tournament_id', 'status', 'scheduled_at']);
        });
        Schema::create('tournament_results', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('fixture_id')->unique()->constrained('tournament_fixtures')->restrictOnDelete();
            $table->foreignUlid('winner_tournament_team_id')->nullable()->constrained('tournament_teams')->restrictOnDelete();
            $table->string('outcome', 24);
            $table->unsignedInteger('home_runs');
            $table->unsignedInteger('home_legal_balls');
            $table->unsignedInteger('away_runs');
            $table->unsignedInteger('away_legal_balls');
            $table->foreignUlid('confirmed_by_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('confirmed_at');
            $table->unsignedBigInteger('version')->default(1);
            $table->timestamps();
        });
        Schema::create('tournament_standings', function (Blueprint $table): void {
            $table->foreignUlid('tournament_id')->constrained('tournaments')->cascadeOnDelete();
            $table->foreignUlid('tournament_team_id')->constrained('tournament_teams')->cascadeOnDelete();
            $table->unsignedInteger('played')->default(0);
            $table->unsignedInteger('won')->default(0);
            $table->unsignedInteger('lost')->default(0);
            $table->unsignedInteger('tied')->default(0);
            $table->unsignedInteger('no_result')->default(0);
            $table->unsignedInteger('points')->default(0);
            $table->decimal('net_run_rate', 10, 4)->default(0);
            $table->unsignedBigInteger('source_version')->default(0);
            $table->timestamps();
            $table->primary(['tournament_id', 'tournament_team_id']);
        });
        Schema::create('tournament_projection_documents', function (Blueprint $table): void {
            $table->foreignUlid('tournament_id')->constrained('tournaments')->cascadeOnDelete();
            $table->string('kind', 32);
            $table->unsignedBigInteger('source_version')->default(0);
            $table->json('payload');
            $table->timestamp('generated_at');
            $table->timestamps();
            $table->primary(['tournament_id', 'kind']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tournament_projection_documents');
        Schema::dropIfExists('tournament_standings');
        Schema::dropIfExists('tournament_results');
        Schema::dropIfExists('tournament_fixtures');
        Schema::dropIfExists('tournament_squad_snapshots');
        Schema::dropIfExists('tournament_teams');
    }
};
