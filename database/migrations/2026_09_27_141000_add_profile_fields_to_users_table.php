<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('city', 120)->nullable()->after('email');
            $table->string('playing_role', 40)->nullable()->after('city');
            $table->string('batting_style', 40)->nullable()->after('playing_role');
            $table->string('bowling_style', 60)->nullable()->after('batting_style');
            $table->text('bio')->nullable()->after('bowling_style');
            $table->string('photo_url', 2048)->nullable()->after('bio');
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['city', 'playing_role', 'batting_style', 'bowling_style', 'bio', 'photo_url']));
    }
};
