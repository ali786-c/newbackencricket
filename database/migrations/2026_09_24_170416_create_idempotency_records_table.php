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
        Schema::create('idempotency_records', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->constrained()->cascadeOnDelete();
            $table->string('operation', 100);
            $table->char('idempotency_key', 26);
            $table->char('request_hash', 64);
            $table->string('resource_type', 50);
            $table->ulid('resource_id');
            $table->unsignedSmallInteger('response_status');
            $table->timestamps();

            $table->unique(['user_id', 'operation', 'idempotency_key'], 'idempotency_actor_operation_key');
            $table->index(['resource_type', 'resource_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('idempotency_records');
    }
};
