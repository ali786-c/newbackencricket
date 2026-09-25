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
        Schema::create('entity_merge_requests', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('entity_type', 24);
            $table->ulid('retired_id');
            $table->ulid('surviving_id');
            $table->foreignUlid('requested_by_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignUlid('decided_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 24)->default('pending');
            $table->text('reason');
            $table->unsignedBigInteger('retired_base_version');
            $table->unsignedBigInteger('surviving_base_version');
            $table->timestamp('requested_at');
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();

            $table->index(['entity_type', 'retired_id', 'status']);
            $table->index(['entity_type', 'surviving_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('entity_merge_requests');
    }
};
