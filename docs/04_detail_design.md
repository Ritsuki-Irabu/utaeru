# 詳細設計書

**バージョン：** 1.0
**作成日：** 2026年6月
**ステータス：** MVP確定

---

## 1. バックエンド詳細設計（Laravel 11）

### 1.1 ルーティング定義（routes/api.php）

```php
<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\SongController;
use App\Http\Controllers\MySongController;
use App\Http\Controllers\TagController;

// 認証不要
Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login',    [AuthController::class, 'login']);
});

// 認証必須
Route::middleware('auth:sanctum')->group(function () {

    Route::post('/auth/logout', [AuthController::class, 'logout']);

    // 公開曲マスタ（閲覧：全員 / 操作：adminのみ）
    Route::get('/songs',             [SongController::class, 'index']);
    Route::get('/songs/spotify',     [SongController::class, 'searchSpotify']);
    Route::middleware('role:admin')->group(function () {
        Route::post('/songs',           [SongController::class, 'store']);
        Route::put('/songs/{song}',     [SongController::class, 'update']);
        Route::delete('/songs/{song}',  [SongController::class, 'destroy']);
    });

    // タグ
    Route::get('/tags',   [TagController::class, 'index']);
    Route::middleware('role:admin')->group(function () {
        Route::post('/tags', [TagController::class, 'store']);
    });

    // マイリスト（本人のみ）
    Route::get('/my-songs',             [MySongController::class, 'index']);
    Route::post('/my-songs',            [MySongController::class, 'store']);
    Route::put('/my-songs/{mySong}',    [MySongController::class, 'update']);
    Route::delete('/my-songs/{mySong}', [MySongController::class, 'destroy']);
    Route::get('/my-songs/export',      [MySongController::class, 'export']);
});
```

---

### 1.2 AuthController

```php
<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(RegisterRequest $request): JsonResponse
    {
        $user = User::create([
            'name'     => $request->name,
            'email'    => $request->email,
            'password' => Hash::make($request->password),
        ]);

        $user->assignRole('user');

        $token = $user->createToken('auth')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user'  => [
                'id'    => $user->id,
                'name'  => $user->name,
                'email' => $user->email,
                'role'  => $user->getRoleNames()->first(),
            ],
        ], 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['メールアドレスまたはパスワードが正しくありません。'],
            ]);
        }

        $token = $user->createToken('auth')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user'  => [
                'id'    => $user->id,
                'name'  => $user->name,
                'email' => $user->email,
                'role'  => $user->getRoleNames()->first(),
            ],
        ]);
    }

    public function logout(): JsonResponse
    {
        auth()->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'ログアウトしました。']);
    }
}
```

---

### 1.3 SongController

```php
<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSongRequest;
use App\Http\Resources\SongResource;
use App\Models\Song;
use App\Services\SpotifyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SongController extends Controller
{
    public function __construct(private SpotifyService $spotify) {}

    public function index(): AnonymousResourceCollection
    {
        $songs = Song::orderBy('title')->paginate(20);
        return SongResource::collection($songs);
    }

    public function store(StoreSongRequest $request): JsonResponse
    {
        $song = Song::create($request->validated());
        return response()->json(new SongResource($song), 201);
    }

    public function update(StoreSongRequest $request, Song $song): JsonResponse
    {
        $song->update($request->validated());
        return response()->json(new SongResource($song));
    }

    public function destroy(Song $song): JsonResponse
    {
        $song->delete();
        return response()->json(['message' => '削除しました。']);
    }

    public function searchSpotify(Request $request): JsonResponse
    {
        $request->validate(['q' => 'required|string|max:100']);

        $results = $this->spotify->searchSong($request->q);
        return response()->json($results);
    }
}
```

---

### 1.4 MySongController

```php
<?php

namespace App\Http\Controllers;

use App\Exports\MySongsExport;
use App\Http\Requests\StoreMySongRequest;
use App\Http\Resources\MySongResource;
use App\Models\MySong;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class MySongController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $mySongs = MySong::with(['song', 'tags'])
            ->where('user_id', auth()->id())
            ->orderBy('created_at', 'desc')
            ->get();

        return MySongResource::collection($mySongs);
    }

    public function store(StoreMySongRequest $request): JsonResponse
    {
        $mySong = MySong::create([
            'user_id' => auth()->id(),
            'song_id' => $request->song_id,
            'memo'    => $request->memo,
        ]);

        if ($request->has('tag_ids')) {
            $mySong->tags()->sync($request->tag_ids);
        }

        $mySong->load(['song', 'tags']);
        return response()->json(new MySongResource($mySong), 201);
    }

    public function update(StoreMySongRequest $request, MySong $mySong): JsonResponse
    {
        $this->authorize('update', $mySong);

        $mySong->update(['memo' => $request->memo]);

        if ($request->has('tag_ids')) {
            $mySong->tags()->sync($request->tag_ids);
        }

        $mySong->load(['song', 'tags']);
        return response()->json(new MySongResource($mySong));
    }

    public function destroy(MySong $mySong): JsonResponse
    {
        $this->authorize('delete', $mySong);

        $mySong->tags()->detach();
        $mySong->delete();

        return response()->json(['message' => '削除しました。']);
    }

    public function export(): BinaryFileResponse
    {
        $fileName = 'my-songs-' . auth()->id() . '-' . now()->format('Ymd') . '.csv';
        return Excel::download(new MySongsExport(auth()->id()), $fileName);
    }
}
```

---

### 1.5 FormRequest（バリデーション）

#### RegisterRequest

```php
<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'      => '名前は必須です。',
            'email.required'     => 'メールアドレスは必須です。',
            'email.unique'       => 'このメールアドレスは既に使用されています。',
            'password.required'  => 'パスワードは必須です。',
            'password.min'       => 'パスワードは8文字以上で入力してください。',
            'password.confirmed' => 'パスワードが一致しません。',
        ];
    }
}
```

#### LoginRequest

```php
<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'email'    => 'required|email',
            'password' => 'required|string',
        ];
    }
}
```

#### StoreSongRequest

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSongRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'title'      => 'required|string|max:255',
            'artist'     => 'required|string|max:255',
            'bpm'        => 'required|integer|min:1|max:300',
            'spotify_id' => 'nullable|string|max:100',
        ];
    }
}
```

#### StoreMySongRequest

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreMySongRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'song_id'   => 'sometimes|required|exists:songs,id',
            'memo'      => 'nullable|string|max:1000',
            'tag_ids'   => 'nullable|array',
            'tag_ids.*' => 'exists:tags,id',
        ];
    }
}
```

---

### 1.6 API Resource（レスポンス整形）

#### SongResource

```php
<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class SongResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'         => $this->id,
            'title'      => $this->title,
            'artist'     => $this->artist,
            'bpm'        => $this->bpm,
            'spotify_id' => $this->spotify_id,
            'created_at' => $this->created_at->format('Y-m-d'),
        ];
    }
}
```

#### MySongResource

```php
<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class MySongResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'   => $this->id,
            'song' => [
                'id'     => $this->song->id,
                'title'  => $this->song->title,
                'artist' => $this->song->artist,
                'bpm'    => $this->song->bpm,
            ],
            'memo'       => $this->memo,
            'tags'       => $this->tags->map(fn($tag) => [
                'id'   => $tag->id,
                'name' => $tag->name,
            ]),
            'created_at' => $this->created_at->format('Y-m-d'),
        ];
    }
}
```

#### TagResource

```php
<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class TagResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'   => $this->id,
            'name' => $this->name,
        ];
    }
}
```

---

### 1.7 モデル定義

#### Song.php

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Song extends Model
{
    protected $fillable = ['title', 'artist', 'bpm', 'spotify_id'];

    public function mySongs(): HasMany
    {
        return $this->hasMany(MySong::class);
    }
}
```

#### MySong.php

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class MySong extends Model
{
    protected $fillable = ['user_id', 'song_id', 'memo'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function song(): BelongsTo
    {
        return $this->belongsTo(Song::class);
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'my_song_tag');
    }
}
```

#### Tag.php

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Tag extends Model
{
    protected $fillable = ['name'];

    public function mySongs(): BelongsToMany
    {
        return $this->belongsToMany(MySong::class, 'my_song_tag');
    }
}
```

---

### 1.8 Policy（本人確認）

#### MySongPolicy.php

```php
<?php

namespace App\Policies;

use App\Models\MySong;
use App\Models\User;

class MySongPolicy
{
    public function update(User $user, MySong $mySong): bool
    {
        return $user->id === $mySong->user_id;
    }

    public function delete(User $user, MySong $mySong): bool
    {
        return $user->id === $mySong->user_id;
    }
}
```

---

### 1.9 SpotifyService

```php
<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class SpotifyService
{
    private string $clientId;
    private string $clientSecret;
    private string $baseUrl = 'https://api.spotify.com/v1';

    public function __construct()
    {
        $this->clientId     = config('services.spotify.client_id');
        $this->clientSecret = config('services.spotify.client_secret');
    }

    public function getAccessToken(): string
    {
        $response = Http::asForm()->withBasicAuth(
            $this->clientId,
            $this->clientSecret
        )->post('https://accounts.spotify.com/api/token', [
            'grant_type' => 'client_credentials',
        ]);

        return $response->json('access_token');
    }

    public function searchSong(string $query): array
    {
        $token = $this->getAccessToken();

        $searchResponse = Http::withToken($token)
            ->get("{$this->baseUrl}/search", [
                'q'     => $query,
                'type'  => 'track',
                'limit' => 5,
            ]);

        $tracks = $searchResponse->json('tracks.items');

        return collect($tracks)->map(function ($track) use ($token) {
            $features = $this->getAudioFeatures($track['id'], $token);
            return [
                'spotify_id' => $track['id'],
                'title'      => $track['name'],
                'artist'     => $track['artists'][0]['name'],
                'bpm'        => (int) round($features['tempo'] ?? 0),
            ];
        })->toArray();
    }

    private function getAudioFeatures(string $spotifyId, string $token): array
    {
        $response = Http::withToken($token)
            ->get("{$this->baseUrl}/audio-features/{$spotifyId}");

        return $response->json();
    }
}
```

---

### 1.10 MySongsExport（CSV出力）

```php
<?php

namespace App\Exports;

use App\Models\MySong;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithCustomCsvSettings;

class MySongsExport implements FromCollection, WithHeadings, WithCustomCsvSettings
{
    public function __construct(private int $userId) {}

    public function collection(): Collection
    {
        return MySong::with(['song', 'tags'])
            ->where('user_id', $this->userId)
            ->get()
            ->map(fn($mySong) => [
                '曲名'         => $mySong->song->title,
                'アーティスト' => $mySong->song->artist,
                'BPM'          => $mySong->song->bpm,
                'タグ'         => $mySong->tags->pluck('name')->join('、'),
                'メモ'         => $mySong->memo ?? '',
                '登録日'       => $mySong->created_at->format('Y/m/d'),
            ]);
    }

    public function headings(): array
    {
        return ['曲名', 'アーティスト', 'BPM', 'タグ', 'メモ', '登録日'];
    }

    public function getCsvSettings(): array
    {
        return [
            'use_bom'   => true,
            'delimiter' => ',',
        ];
    }
}
```

---

### 1.11 マイグレーション

#### songs テーブル

```php
Schema::create('songs', function (Blueprint $table) {
    $table->id();
    $table->string('title');
    $table->string('artist');
    $table->unsignedInteger('bpm');
    $table->string('spotify_id', 100)->nullable();
    $table->timestamps();
});
```

#### tags テーブル

```php
Schema::create('tags', function (Blueprint $table) {
    $table->id();
    $table->string('name', 100)->unique();
    $table->timestamps();
});
```

#### my_songs テーブル

```php
Schema::create('my_songs', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->foreignId('song_id')->constrained()->cascadeOnDelete();
    $table->text('memo')->nullable();
    $table->timestamps();
});
```

#### my_song_tag 中間テーブル

```php
Schema::create('my_song_tag', function (Blueprint $table) {
    $table->foreignId('my_song_id')->constrained('my_songs')->cascadeOnDelete();
    $table->foreignId('tag_id')->constrained()->cascadeOnDelete();
    $table->primary(['my_song_id', 'tag_id']);
});
```

---

### 1.12 Seeder

```php
<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Tag;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        Role::create(['name' => 'admin']);
        Role::create(['name' => 'user']);

        $admin = User::create([
            'name'     => '管理者',
            'email'    => 'admin@example.com',
            'password' => bcrypt('password'),
        ]);
        $admin->assignRole('admin');

        $user = User::create([
            'name'     => 'テストユーザー',
            'email'    => 'user@example.com',
            'password' => bcrypt('password'),
        ]);
        $user->assignRole('user');

        collect(['盛り上がる', 'しっとり', 'アップテンポ', 'バラード', '定番'])
            ->each(fn($name) => Tag::create(['name' => $name]));
    }
}
```

---

## 2. フロントエンド詳細設計（Vue.js 3 PWA）

### 2.1 api/index.js（Axios共通設定）

```js
import axios from 'axios'

const api = axios.create({
  baseURL: import.meta.env.VITE_API_URL,
  headers: { 'Content-Type': 'application/json' },
})

api.interceptors.request.use(config => {
  const token = localStorage.getItem('token')
  if (token) config.headers.Authorization = `Bearer ${token}`
  return config
})

api.interceptors.response.use(
  response => response,
  error => {
    if (error.response?.status === 401) {
      localStorage.removeItem('token')
      window.location.href = '/login'
    }
    return Promise.reject(error)
  }
)

export default api
```

---

### 2.2 stores/auth.js（認証ストア）

```js
import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import api from '@/api/index'

export const useAuthStore = defineStore('auth', () => {
  const user  = ref(null)
  const token = ref(localStorage.getItem('token'))

  const isLoggedIn = computed(() => !!token.value)
  const isAdmin    = computed(() => user.value?.role === 'admin')

  const login = async (email, password) => {
    const res = await api.post('/auth/login', { email, password })
    token.value = res.data.token
    user.value  = res.data.user
    localStorage.setItem('token', token.value)
  }

  const logout = async () => {
    await api.post('/auth/logout')
    token.value = null
    user.value  = null
    localStorage.removeItem('token')
  }

  return { user, token, isLoggedIn, isAdmin, login, logout }
})
```

---

### 2.3 router/index.js

```js
import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const routes = [
  { path: '/login',       component: () => import('@/pages/Login.vue'),      meta: { guest: true } },
  { path: '/',            component: () => import('@/pages/MySongs.vue'),     meta: { auth: true } },
  { path: '/songs',       component: () => import('@/pages/SongSearch.vue'),  meta: { auth: true } },
  { path: '/admin/songs', component: () => import('@/pages/Admin/Songs.vue'), meta: { auth: true, admin: true } },
]

const router = createRouter({
  history: createWebHistory(),
  routes,
})

router.beforeEach((to) => {
  const auth = useAuthStore()

  if (to.meta.auth && !auth.isLoggedIn) return '/login'
  if (to.meta.guest && auth.isLoggedIn) return '/'
  if (to.meta.admin && !auth.isAdmin)   return '/'
})

export default router
```

---

### 2.4 RhythmPlayer.vue（リズム再生コンポーネント）

```vue
<template>
  <div class="rhythm-player">
    <p class="song-title">{{ song.title }}</p>
    <p class="bpm">BPM: {{ song.bpm }}</p>

    <div class="beats">
      <div
        v-for="beat in 4"
        :key="beat"
        class="beat"
        :class="{
          active: currentBeat === beat,
          strong: beat === 1
        }"
      />
    </div>

    <div class="controls">
      <button v-if="!isPlaying" @click="start" class="btn-start">▶ スタート</button>
      <button v-else            @click="stop"  class="btn-stop">■ ストップ</button>
    </div>
  </div>
</template>

<script setup>
import { ref, onUnmounted } from 'vue'

const props = defineProps({
  song: { type: Object, required: true },
})

const isPlaying   = ref(false)
const currentBeat = ref(0)
let timer = null
let beat  = 0

const start = () => {
  isPlaying.value = true
  beat = 0
  const interval = 60000 / props.song.bpm

  timer = setInterval(() => {
    beat = (beat % 4) + 1
    currentBeat.value = beat
  }, interval)
}

const stop = () => {
  isPlaying.value   = false
  currentBeat.value = 0
  clearInterval(timer)
  timer = null
}

onUnmounted(() => {
  if (timer) clearInterval(timer)
})
</script>

<style scoped>
.beats {
  display: flex;
  gap: 16px;
  justify-content: center;
  margin: 24px 0;
}
.beat {
  width: 40px;
  height: 40px;
  border-radius: 50%;
  background: #333;
  transition: all 0.05s ease;
}
.beat.strong {
  width: 60px;
  height: 60px;
}
.beat.active {
  background: #fff;
  box-shadow: 0 0 20px #fff;
}
</style>
```

---

### 2.5 .env 設定

#### バックエンド（.env）

```
APP_NAME=ウタエル
APP_URL=http://localhost:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=utaeru
DB_USERNAME=root
DB_PASSWORD=

SPOTIFY_CLIENT_ID=your_spotify_client_id
SPOTIFY_CLIENT_SECRET=your_spotify_client_secret
```

#### フロントエンド（.env）

```
VITE_API_URL=http://localhost:8000/api
```

---

### 2.6 vite.config.js（PWA設定）

```js
import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import { VitePWA } from 'vite-plugin-pwa'

export default defineConfig({
  plugins: [
    vue(),
    VitePWA({
      registerType: 'autoUpdate',
      manifest: {
        name:             'ウタエル',
        short_name:       'ウタエル',
        description:      '符割り確認特化型カラオケ支援アプリ',
        theme_color:      '#000000',
        background_color: '#000000',
        display:          'standalone',
        start_url:        '/',
        icons: [
          { src: '/icon-192.png', sizes: '192x192', type: 'image/png' },
          { src: '/icon-512.png', sizes: '512x512', type: 'image/png' },
        ],
      },
    }),
  ],
})
```
