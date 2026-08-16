<?php

namespace App\Contracts;

interface LyricsProvider
{
    /**
     * 利用許諾済みの歌詞サービスから、曲に紐づく歌詞を取得する。
     *
     * @return array{lyrics: string, source_url: string|null}|null
     */
    public function find(string $title, string $artist): ?array;
}
