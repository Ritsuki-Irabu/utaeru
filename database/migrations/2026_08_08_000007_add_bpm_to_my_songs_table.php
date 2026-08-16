<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('my_songs', function (Blueprint $table): void {
            // 曲マスタの値を上書きせず、ユーザーが実測した値を保存する。
            $table->unsignedSmallInteger('bpm')->nullable()->after('memo');
        });
    }

    public function down(): void
    {
        Schema::table('my_songs', function (Blueprint $table): void {
            $table->dropColumn('bpm');
        });
    }
};
