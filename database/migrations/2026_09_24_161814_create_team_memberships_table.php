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
        Schema::create('team_memberships', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('team_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('player_id')->constrained()->restrictOnDelete();
            $table->string('status', 32)->default('active');
            $table->string('team_role', 50)->nullable();
            $table->timestamp('joined_at');
            $table->timestamp('left_at')->nullable();
            $table->unsignedBigInteger('version')->default(1);
            $table->timestamps();

            $table->index(['team_id', 'status', 'joined_at']);
            $table->index(['player_id', 'status', 'joined_at']);
            $table->index(['team_id', 'player_id', 'joined_at', 'left_at'], 'membership_interval_lookup');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('team_memberships');
    }
};
