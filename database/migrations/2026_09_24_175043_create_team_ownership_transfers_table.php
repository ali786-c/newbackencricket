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
        Schema::create('team_ownership_transfers', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('team_id')->constrained('teams')->restrictOnDelete();
            $table->foreignUlid('from_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignUlid('to_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignUlid('decided_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 24)->default('pending');
            $table->text('message')->nullable();
            $table->text('decision_reason')->nullable();
            $table->unsignedBigInteger('team_version_at_request');
            $table->timestamp('requested_at');
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();

            $table->index(['team_id', 'status']);
            $table->index(['to_user_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('team_ownership_transfers');
    }
};
