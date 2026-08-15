<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('songs', function (Blueprint $table) {
            $table->string('playback_provider', 30)->nullable();
            $table->string('playback_key', 255)->nullable();
            $table->string('playback_url', 500)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('songs', function (Blueprint $table) {
            $table->dropColumn([
                'playback_provider',
                'playback_key',
                'playback_url',
            ]);
        });
    }
};
