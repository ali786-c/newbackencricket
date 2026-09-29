<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('matches', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('created_by_user_id')->constrained('users')->restrictOnDelete();
            $table->string('match_type', 24)->default('simple');
            $table->string('status', 24)->default('draft')->index();
            $table->foreignUlid('home_team_id')->constrained('teams')->restrictOnDelete();
            $table->foreignUlid('away_team_id')->constrained('teams')->restrictOnDelete();
            $table->timestamp('scheduled_at_utc');
            $table->string('venue', 160);
            $table->unsignedBigInteger('server_version')->default(0);
            $table->unsignedBigInteger('last_sequence')->default(0);
            $table->timestamp('finalized_at')->nullable()->index();
            $table->json('result_json')->nullable();
            $table->timestamps();
            $table->index(['created_by_user_id', 'status']);
        });

        Schema::table('match_team_snapshots', function (Blueprint $table): void {
            $table->string('side', 8)->nullable()->after('source_team_id');
            $table->string('team_code', 14)->nullable()->after('short_name');
            $table->unique(['match_id', 'side']);
        });

        Schema::table('match_player_snapshots', function (Blueprint $table): void {
            $table->string('player_code', 14)->nullable()->after('name');
            $table->unique(['match_id', 'source_player_id']);
        });

        Schema::create('match_rule_profiles', function (Blueprint $table): void {
            $table->foreignUlid('match_id')->constrained('matches')->restrictOnDelete();
            $table->unsignedInteger('version');
            $table->unsignedSmallInteger('overs_per_innings');
            $table->unsignedTinyInteger('balls_per_over')->default(6);
            $table->unsignedTinyInteger('players_per_side');
            $table->unsignedTinyInteger('wickets_per_innings');
            $table->string('ball_type', 20);
            $table->timestamps();
            $table->primary(['match_id', 'version']);
        });

        Schema::create('innings', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('match_id')->constrained('matches')->restrictOnDelete();
            $table->unsignedTinyInteger('innings_number');
            $table->string('status', 24)->default('pending');
            $table->foreignUlid('batting_team_snapshot_id')->constrained('match_team_snapshots')->restrictOnDelete();
            $table->foreignUlid('bowling_team_snapshot_id')->constrained('match_team_snapshots')->restrictOnDelete();
            $table->timestamp('started_at_utc')->nullable();
            $table->timestamp('completed_at_utc')->nullable();
            $table->timestamps();
            $table->unique(['match_id', 'innings_number']);
        });

        Schema::create('match_scoring_sessions', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('match_id')->constrained('matches')->restrictOnDelete();
            $table->foreignUlid('user_id')->constrained('users')->restrictOnDelete();
            $table->ulid('device_id');
            $table->string('state', 20)->default('active');
            $table->unsignedBigInteger('base_server_version');
            $table->timestamp('acquired_at_utc');
            $table->timestamp('last_heartbeat_at_utc');
            $table->timestamp('expires_at_utc')->index();
            $table->timestamp('released_at_utc')->nullable();
            $table->timestamps();
            $table->index(['match_id', 'state']);
        });

        Schema::create('match_events', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('match_id')->constrained('matches')->restrictOnDelete();
            $table->foreignUlid('innings_id')->nullable()->constrained('innings')->restrictOnDelete();
            $table->foreignUlid('scoring_session_id')->constrained('match_scoring_sessions')->restrictOnDelete();
            $table->ulid('device_id');
            $table->unsignedBigInteger('sequence');
            $table->string('event_type', 40);
            $table->unsignedSmallInteger('event_schema_version')->default(1);
            $table->unsignedInteger('rule_profile_version');
            $table->unsignedBigInteger('base_server_version');
            $table->timestamp('occurred_at_utc');
            $table->json('payload_json');
            $table->foreignUlid('supersedes_event_id')->nullable()->constrained('match_events')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['match_id', 'sequence']);
            $table->index(['match_id', 'innings_id', 'sequence']);
        });

        Schema::create('event_corrections', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('match_id')->constrained('matches')->restrictOnDelete();
            $table->foreignUlid('target_event_id')->constrained('match_events')->restrictOnDelete();
            $table->foreignUlid('correction_event_id')->constrained('match_events')->restrictOnDelete();
            $table->foreignUlid('created_by_user_id')->constrained('users')->restrictOnDelete();
            $table->string('reason', 500)->nullable();
            $table->timestamps();
        });

        Schema::create('match_projections', function (Blueprint $table): void {
            $table->foreignUlid('match_id')->primary()->constrained('matches')->restrictOnDelete();
            $table->unsignedBigInteger('source_sequence')->default(0);
            $table->unsignedBigInteger('server_version')->default(0);
            $table->unsignedSmallInteger('schema_version')->default(1);
            $table->json('payload_json');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('match_projections');
        Schema::dropIfExists('event_corrections');
        Schema::dropIfExists('match_events');
        Schema::dropIfExists('match_scoring_sessions');
        Schema::dropIfExists('innings');
        Schema::dropIfExists('match_rule_profiles');
        Schema::table('match_player_snapshots', function (Blueprint $table): void {
            $table->dropUnique(['match_id', 'source_player_id']);
            $table->dropColumn('player_code');
        });
        Schema::table('match_team_snapshots', function (Blueprint $table): void {
            $table->dropUnique(['match_id', 'side']);
            $table->dropColumn(['side', 'team_code']);
        });
        Schema::dropIfExists('matches');
    }
};
