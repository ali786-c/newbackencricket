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
        Schema::create('sync_operations', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->constrained()->cascadeOnDelete();
            $table->ulid('outbox_id');
            $table->string('operation', 120);
            $table->string('entity_type', 60);
            $table->ulid('entity_id');
            $table->json('payload');
            $table->char('request_hash', 64);
            $table->string('status', 30)->default('accepted');
            $table->timestamp('accepted_at');
            $table->timestamps();

            $table->unique(['user_id', 'outbox_id']);
            $table->index(['entity_type', 'entity_id', 'accepted_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sync_operations');
    }
};
