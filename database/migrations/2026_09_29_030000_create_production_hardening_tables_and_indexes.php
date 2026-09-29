<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sync_health_events', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('category', 20);
            $table->string('operation', 120);
            $table->unsignedSmallInteger('http_status');
            $table->foreignUlid('user_id')->nullable()->constrained()->nullOnDelete();
            $table->char('device_id_hash', 64)->nullable();
            $table->timestamp('created_at');
            $table->index(['category', 'created_at']);
            $table->index(['operation', 'created_at']);
        });

        Schema::table('sync_operations', function (Blueprint $table): void {
            $table->index(['user_id', 'status', 'accepted_at'], 'sync_user_status_time_idx');
        });
        Schema::table('match_events', function (Blueprint $table): void {
            $table->index(['match_id', 'created_at'], 'match_events_match_created_idx');
        });
        Schema::table('media_uploads', function (Blueprint $table): void {
            $table->index(['status', 'updated_at'], 'media_status_updated_idx');
        });
    }

    public function down(): void
    {
        Schema::table('media_uploads', fn (Blueprint $table) => $table->dropIndex('media_status_updated_idx'));
        Schema::table('match_events', fn (Blueprint $table) => $table->dropIndex('match_events_match_created_idx'));
        Schema::table('sync_operations', fn (Blueprint $table) => $table->dropIndex('sync_user_status_time_idx'));
        Schema::dropIfExists('sync_health_events');
    }
};
