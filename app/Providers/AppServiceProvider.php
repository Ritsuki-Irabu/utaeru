<?php

namespace App\Providers;

use App\Contracts\MusicSearchProvider;
use App\Contracts\LyricsProvider;
use App\Contracts\VideoSearchProvider;
use App\Services\AuthorizedLyricsProvider;
use App\Services\ItunesMusicProvider;
use App\Services\YoutubeVideoProvider;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(MusicSearchProvider::class, ItunesMusicProvider::class);
        $this->app->bind(LyricsProvider::class, AuthorizedLyricsProvider::class);
        $this->app->bind(VideoSearchProvider::class, YoutubeVideoProvider::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
