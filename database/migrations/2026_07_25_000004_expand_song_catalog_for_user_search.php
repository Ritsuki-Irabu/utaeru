<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('songs', function (Blueprint $table) {
            $table->string('album')->nullable()->after('artist');
            $table->string('artwork_url', 500)->nullable()->after('album');
            $table->unsignedInteger('duration_ms')->nullable()->after('bpm');
            $table->unique(['playback_provider', 'playback_key'], 'songs_provider_key_unique');
        });

        Schema::table('songs', function (Blueprint $table) {
            $table->unsignedInteger('bpm')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('songs', function (Blueprint $table) {
            $table->dropUnique('songs_provider_key_unique');
            $table->dropColumn(['album', 'artwork_url', 'duration_ms']);
        });

        Schema::table('songs', function (Blueprint $table) {
            $table->unsignedInteger('bpm')->nullable(false)->change();
        });
    }
};
