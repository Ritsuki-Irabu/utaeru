<?php

namespace App\Services;

use Throwable;

class BpmResolver
{
    public function __construct(
        private readonly DeezerService $deezer,
        private readonly GetSongBpmService $getSongBpm,
        private readonly SpotifyService $spotify,
        private readonly YoutubeVideoProvider $youtube,
    ) {}

    public function resolve(string $title, string $artist, ?int $durationMs = null, ?string $album = null): ?int
    {
        if (blank($title) || blank($artist)) {
            return null;
        }

        // Deezerは公開メタデータで最初に試し、0/nullの場合だけSpotifyを補助的に使う。
        // 片方の障害や未設定で、もう片方の取得機会を失わない。
        try {
            $bpm = $this->deezer->findBpm($title, $artist, $durationMs, $album);

            if ($bpm !== null) {
                return $bpm;
            }
        } catch (Throwable) {
            // 次のプロバイダへ進む。
        }

        try {
            $bpm = $this->getSongBpm->findBpm($title, $artist);

            if ($bpm !== null) {
                return $bpm;
            }
        } catch (Throwable) {
            // 次のプロバイダへ進む。
        }

        try {
            $bpm = $this->spotify->findBpm($title, $artist, $durationMs, $album);

            if ($bpm !== null) {
                return $bpm;
            }
        } catch (Throwable) {
            // YouTubeのメタデータ補完へ進む。
        }

        try {
            return $this->youtube->findBpmFromMetadata($title, $artist);
        } catch (Throwable) {
            // BPM取得はカタログ登録を止める必須処理ではないため、
            // 外部API障害時は未登録のまま登録を継続する。
            return null;
        }
    }
}
