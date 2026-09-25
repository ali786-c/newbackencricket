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
        Schema::create('player_invitations', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('player_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('created_by_user_id')->constrained('users')->restrictOnDelete();
            $table->string('contact_type', 20);
            $table->text('encrypted_contact_value');
            $table->string('status', 20)->default('pending');
            $table->timestamps();
        });

        Schema::create('match_team_snapshots', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('match_id')->index();
            $table->foreignUlid('source_team_id')->constrained('teams')->restrictOnDelete();
            $table->string('name', 100);
            $table->string('short_name', 4);
            $table->timestamps();
        });

        Schema::create('match_player_snapshots', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('match_id')->index();
            $table->foreignUlid('source_player_id')->constrained('players')->restrictOnDelete();
            $table->foreignUlid('match_team_snapshot_id')->constrained()->restrictOnDelete();
            $table->string('name', 100);
            $table->string('playing_role', 32);
            $table->string('squad_role', 20)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('match_player_snapshots');
        Schema::dropIfExists('match_team_snapshots');
        Schema::dropIfExists('player_invitations');
    }
};
