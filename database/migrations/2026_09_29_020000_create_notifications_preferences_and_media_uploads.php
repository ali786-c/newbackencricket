<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teams', function (Blueprint $table): void {
            $table->string('logo_path')->nullable()->after('description');
        });
        Schema::create('user_notifications', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('category', 32);
            $table->string('title', 160);
            $table->text('body');
            $table->string('deep_link')->nullable();
            $table->json('data')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'created_at']);
            $table->index(['user_id', 'read_at']);
        });

        Schema::create('notification_preferences', function (Blueprint $table): void {
            $table->foreignUlid('user_id')->primary()->constrained('users')->cascadeOnDelete();
            $table->boolean('match_updates')->default(true);
            $table->boolean('tournament_updates')->default(true);
            $table->boolean('team_updates')->default(true);
            $table->boolean('system_updates')->default(true);
            $table->boolean('marketing')->default(false);
            $table->timestamps();
        });

        Schema::create('media_uploads', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('purpose', 32);
            $table->string('owner_type', 32);
            $table->ulid('owner_id')->nullable();
            $table->string('status', 24)->default('pending');
            $table->string('disk', 32)->default('public');
            $table->string('path')->nullable();
            $table->string('original_name')->nullable();
            $table->string('mime_type', 100)->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->string('error_code', 64)->nullable();
            $table->timestamps();
            $table->index(['user_id', 'status']);
            $table->index(['owner_type', 'owner_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_uploads');
        Schema::dropIfExists('notification_preferences');
        Schema::dropIfExists('user_notifications');
        Schema::table('teams', function (Blueprint $table): void {
            $table->dropColumn('logo_path');
        });
    }
};
