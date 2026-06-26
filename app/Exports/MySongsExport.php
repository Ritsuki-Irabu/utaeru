<?php

namespace App\Exports;

use App\Models\MySong;
use App\Models\User;
use Illuminate\Support\Collection;

class MySongsExport
{
    public function __construct(
        private readonly User $user
    ) {}

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return [
            '曲名',
            'アーティスト',
            'BPM',
            'メモ',
            'タグ',
            '追加日',
        ];
    }

    /**
     * @return Collection<int, array<int, string|int|null>>
     */
    public function rows(): Collection
    {
        // ログインユーザー本人のマイリストだけをCSV用の行データに変換する
        return MySong::with(['song', 'tags'])
            ->where('user_id', $this->user->id)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn (MySong $mySong) => [
                $mySong->song?->title,
                $mySong->song?->artist,
                $mySong->song?->bpm,
                $mySong->memo,
                $mySong->tags->pluck('name')->implode(', '),
                $mySong->created_at?->format('Y-m-d'),
            ]);
    }

    public function filename(): string
    {
        return 'my-songs.csv';
    }

    public function stream(): void
    {
        $handle = fopen('php://output', 'w');

        // Excelで開いたときの文字化けを防ぐため、UTF-8 BOMを先頭に付ける
        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, $this->headings());

        foreach ($this->rows() as $row) {
            fputcsv($handle, $row);
        }

        fclose($handle);
    }
}
