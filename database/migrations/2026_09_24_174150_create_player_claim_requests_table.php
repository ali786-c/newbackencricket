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
        Schema::create('player_claim_requests', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('player_id')->constrained('players')->restrictOnDelete();
            $table->foreignUlid('claimant_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignUlid('decided_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 24)->default('pending');
            $table->text('request_message')->nullable();
            $table->text('decision_reason')->nullable();
            $table->unsignedBigInteger('player_version_at_request');
            $table->timestamp('requested_at');
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();

            $table->index(['player_id', 'status']);
            $table->index(['claimant_user_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('player_claim_requests');
    }
};
