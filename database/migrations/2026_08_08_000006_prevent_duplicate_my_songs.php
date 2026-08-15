<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 既存の重複は最古のレコードへメモ・タグを統合してから削除する。
        DB::table('my_songs')
            ->select('user_id', 'song_id')
            ->groupBy('user_id', 'song_id')
            ->havingRaw('COUNT(*) > 1')
            ->get()
            ->each(function (object $group): void {
                $records = DB::table('my_songs')
                    ->where('user_id', $group->user_id)
                    ->where('song_id', $group->song_id)
                    ->orderBy('id')
                    ->get();
                $keeper = $records->first();

                if ($keeper === null) {
                    return;
                }

                foreach ($records->skip(1) as $duplicate) {
                    if (blank($keeper->memo) && filled($duplicate->memo)) {
                        DB::table('my_songs')
                            ->where('id', $keeper->id)
                            ->update(['memo' => $duplicate->memo]);
                        $keeper->memo = $duplicate->memo;
                    }

                    $tagIds = DB::table('my_song_tag')
                        ->where('my_song_id', $duplicate->id)
                        ->pluck('tag_id')
                        ->map(fn ($tagId): array => [
                            'my_song_id' => $keeper->id,
                            'tag_id' => $tagId,
                        ])
                        ->all();

                    if ($tagIds !== []) {
                        DB::table('my_song_tag')->insertOrIgnore($tagIds);
                    }

                    DB::table('my_songs')->where('id', $duplicate->id)->delete();
                }
            });

        Schema::table('my_songs', function (Blueprint $table): void {
            $table->unique(['user_id', 'song_id'], 'my_songs_user_song_unique');
        });
    }

    public function down(): void
    {
        Schema::table('my_songs', function (Blueprint $table): void {
            $table->dropUnique('my_songs_user_song_unique');
        });
    }
};
