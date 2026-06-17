<?php

namespace Database\Seeders;

use App\Models\MySong;
use App\Models\Song;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $user = User::updateOrCreate(
            ['email' => 'user@example.com'],
            [
                'name' => 'テストユーザー',
                'password' => Hash::make('password'),
            ],
        );

        $songs = collect([
            [
                'title' => 'Pretender',
                'artist' => 'Official髭男dism',
                'bpm' => 92,
                'spotify_id' => null,
            ],
            [
                'title' => '残酷な天使のテーゼ',
                'artist' => '高橋洋子',
                'bpm' => 128,
                'spotify_id' => null,
            ],
        ])->map(fn (array $song) => Song::updateOrCreate(
            [
                'title' => $song['title'],
                'artist' => $song['artist'],
            ],
            $song,
        ));

        $tags = collect(['高音注意', 'サビ練習', '定番'])
            ->mapWithKeys(fn (string $name) => [
                $name => Tag::firstOrCreate(['name' => $name]),
            ]);

        $mySong = MySong::firstOrCreate(
            [
                'user_id' => $user->id,
                'song_id' => $songs[0]->id,
            ],
            ['memo' => 'サビ前の入りを確認する'],
        );
        $mySong->tags()->syncWithoutDetaching([
            $tags['高音注意']->id,
            $tags['サビ練習']->id,
        ]);

        $mySong = MySong::firstOrCreate(
            [
                'user_id' => $user->id,
                'song_id' => $songs[1]->id,
            ],
            ['memo' => 'テンポを体で覚える'],
        );
        $mySong->tags()->syncWithoutDetaching([
            $tags['定番']->id,
        ]);
    }
}
