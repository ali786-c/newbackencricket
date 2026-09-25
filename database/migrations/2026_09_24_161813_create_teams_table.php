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
        Schema::create('teams', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->char('team_code', 14)->unique();
            $table->foreignUlid('owner_user_id')->constrained('users')->restrictOnDelete();
            $table->string('name', 100);
            $table->string('normalized_name', 100)->index();
            $table->string('short_name', 4);
            $table->string('city', 100);
            $table->text('description')->nullable();
            $table->unsignedBigInteger('version')->default(1);
            $table->timestamp('archived_at')->nullable()->index();
            $table->timestamps();

            $table->index(['owner_user_id', 'archived_at']);
            $table->index(['city', 'normalized_name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('teams');
    }
};
