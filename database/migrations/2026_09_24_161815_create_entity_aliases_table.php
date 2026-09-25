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
        Schema::create('entity_aliases', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('entity_type', 32);
            $table->ulid('retired_id');
            $table->ulid('surviving_id');
            $table->foreignUlid('actor_user_id')->constrained('users')->restrictOnDelete();
            $table->string('reason', 500);
            $table->timestamp('created_at');

            $table->unique(['entity_type', 'retired_id']);
            $table->index(['entity_type', 'surviving_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('entity_aliases');
    }
};
