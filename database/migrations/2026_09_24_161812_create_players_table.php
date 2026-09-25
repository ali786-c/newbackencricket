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
        Schema::create('players', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->char('player_code', 14)->unique();
            $table->foreignUlid('claimed_user_id')->nullable()->unique()->constrained('users')->nullOnDelete();
            $table->foreignUlid('created_by_user_id')->constrained('users')->restrictOnDelete();
            $table->string('name', 100);
            $table->string('normalized_name', 100)->index();
            $table->string('city', 100)->nullable();
            $table->string('playing_role', 32);
            $table->string('batting_style', 32);
            $table->string('bowling_style', 100);
            $table->text('bio')->nullable();
            $table->string('claim_status', 32)->default('unclaimed');
            $table->unsignedBigInteger('version')->default(1);
            $table->timestamp('archived_at')->nullable()->index();
            $table->timestamps();

            $table->index(['city', 'normalized_name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('players');
    }
};
