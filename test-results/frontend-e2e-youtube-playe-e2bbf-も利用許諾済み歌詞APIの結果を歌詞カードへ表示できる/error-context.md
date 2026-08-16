# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: frontend/e2e/youtube-player.spec.js >> 曲詳細のYouTube MV再生 >> 登録歌詞がない場合も利用許諾済み歌詞APIの結果を歌詞カードへ表示できる
- Location: frontend/e2e/youtube-player.spec.js:198:5

# Error details

```
Error: page.goto: Protocol error (Page.navigate): Cannot navigate to invalid URL
Call log:
  - navigating to "/songs/1", waiting until "load"

```

# Test source

```ts
  121 | 
  122 |         await page.route('**://localhost/api/songs/youtube/search**', async (route) => {
  123 |             await route.fulfill({
  124 |                 status: 502,
  125 |                 contentType: 'application/json',
  126 |                 body: JSON.stringify({ message: 'YouTube APIへの接続に失敗しました。' }),
  127 |             })
  128 |         })
  129 | 
  130 |         await page.goto('/songs/1')
  131 |         await page.getByRole('button', { name: 'PretenderのMVを再生' }).click()
  132 | 
  133 |         await expect(page.getByRole('alert')).toContainText('YouTube APIへの接続に失敗しました。')
  134 |         await expect(page.getByRole('button', { name: 'PretenderのMVを再生' })).toBeVisible()
  135 |         await expect(page.getByText('YouTubeで動画を検索')).toHaveCount(0)
  136 |     })
  137 | 
  138 |     test('埋め込み再生できないMVは次の候補へ切り替えられる', async ({ page }) => {
  139 |         await authenticate(page)
  140 | 
  141 |         await page.route('**://localhost/api/songs/1', async (route) => {
  142 |             await route.fulfill({
  143 |                 status: 200,
  144 |                 contentType: 'application/json',
  145 |                 body: JSON.stringify({ data: songs[0] }),
  146 |             })
  147 |         })
  148 | 
  149 |         await page.route('**://localhost/api/songs/youtube/search**', async (route) => {
  150 |             await route.fulfill({
  151 |                 status: 200,
  152 |                 contentType: 'application/json',
  153 |                 body: JSON.stringify({
  154 |                     data: [
  155 |                         { provider: 'youtube', video_id: 'blocked123', title: '埋め込み不可MV', is_embeddable: true },
  156 |                         { provider: 'youtube', video_id: 'fallback123', title: '代替MV', is_embeddable: true },
  157 |                     ],
  158 |                 }),
  159 |             })
  160 |         })
  161 | 
  162 |         await page.goto('/songs/1')
  163 |         await page.getByRole('button', { name: 'PretenderのMVを再生' }).click()
  164 |         await expect(page.locator('iframe.youtube-embed')).toHaveAttribute('src', /blocked123/)
  165 | 
  166 |         await page.evaluate(() => {
  167 |             window.dispatchEvent(new MessageEvent('message', {
  168 |                 origin: 'https://www.youtube.com',
  169 |                 data: JSON.stringify({ event: 'onError', info: 150 }),
  170 |             }))
  171 |         })
  172 | 
  173 |         await expect(page.locator('iframe.youtube-embed')).toHaveAttribute('src', /fallback123/)
  174 |     })
  175 | 
  176 |     test('MV検索の通信が切断された場合に再試行導線を表示できる', async ({ page }) => {
  177 |         await authenticate(page)
  178 | 
  179 |         await page.route('**://localhost/api/songs/1', async (route) => {
  180 |             await route.fulfill({
  181 |                 status: 200,
  182 |                 contentType: 'application/json',
  183 |                 body: JSON.stringify({ data: songs[0] }),
  184 |             })
  185 |         })
  186 | 
  187 |         await page.route('**://localhost/api/songs/youtube/search**', async (route) => {
  188 |             await route.abort('internetdisconnected')
  189 |         })
  190 | 
  191 |         await page.goto('/songs/1')
  192 |         await page.getByRole('button', { name: 'PretenderのMVを再生' }).click()
  193 | 
  194 |         await expect(page.getByRole('alert')).toContainText('この曲のMVを見つけられませんでした')
  195 |         await expect(page.getByRole('button', { name: 'PretenderのMVを再生' })).toBeVisible()
  196 |     })
  197 | 
  198 |     test('登録歌詞がない場合も利用許諾済み歌詞APIの結果を歌詞カードへ表示できる', async ({ page }) => {
  199 |         await authenticate(page)
  200 | 
  201 |         await page.route('**://localhost/api/songs/1', async (route) => {
  202 |             await route.fulfill({
  203 |                 status: 200,
  204 |                 contentType: 'application/json',
  205 |                 body: JSON.stringify({ data: { ...songs[0], lyrics: null } }),
  206 |             })
  207 |         })
  208 |         await page.route('**://localhost/api/songs/1/lyrics', async (route) => {
  209 |             await route.fulfill({
  210 |                 status: 200,
  211 |                 contentType: 'application/json',
  212 |                 body: JSON.stringify({
  213 |                     data: {
  214 |                         lyrics: '提供元の歌い出しです。\nサビの歌詞です。',
  215 |                         source_url: 'https://licensed.example.test/songs/pretender',
  216 |                     },
  217 |                 }),
  218 |             })
  219 |         })
  220 | 
> 221 |         await page.goto('/songs/1')
      |                    ^ Error: page.goto: Protocol error (Page.navigate): Cannot navigate to invalid URL
  222 | 
  223 |         await expect(page.getByText('提供元の歌い出しです。')).toBeVisible()
  224 |         await expect(page.getByRole('link', { name: '歌詞提供元を開く' })).toHaveAttribute(
  225 |             'href',
  226 |             'https://licensed.example.test/songs/pretender',
  227 |         )
  228 |     })
  229 | 
  230 |     test('曲詳細カテゴリをドラッグで並べ替え、再訪時も順番を保持できる', async ({ page }) => {
  231 |         await authenticate(page)
  232 | 
  233 |         await page.route('**://localhost/api/songs/1', async (route) => {
  234 |             await route.fulfill({
  235 |                 status: 200,
  236 |                 contentType: 'application/json',
  237 |                 body: JSON.stringify({ data: { ...songs[0], lyrics: '歌詞カードの確認用です。' } }),
  238 |             })
  239 |         })
  240 | 
  241 |         await page.goto('/songs/1')
  242 | 
  243 |         const categoryList = page.locator('.song-detail-category-list > .song-detail-category')
  244 |         await expect(categoryList).toHaveCount(4)
  245 |         await expect(page.locator('[data-category="rhythm"]')).toBeVisible()
  246 | 
  247 |         const sourceHandle = page.locator('[data-category="lyrics"] .song-detail-drag-handle')
  248 |         const targetCategory = page.locator('[data-category="rhythm"]')
  249 |         await sourceHandle.scrollIntoViewIfNeeded()
  250 |         const sourceBox = await sourceHandle.boundingBox()
  251 |         const targetBox = await targetCategory.boundingBox()
  252 | 
  253 |         await page.mouse.move(sourceBox.x + sourceBox.width / 2, sourceBox.y + sourceBox.height / 2)
  254 |         await page.mouse.down()
  255 |         await expect(page.locator('[data-category="lyrics"].is-dragging')).toBeVisible()
  256 |         await page.mouse.move(targetBox.x + targetBox.width / 2, targetBox.y + targetBox.height / 2, { steps: 8 })
  257 |         await page.mouse.up()
  258 | 
  259 |         await expect.poll(async () => page.locator('.song-detail-category-list > .song-detail-category').evaluateAll(
  260 |             (elements) => elements.map((element) => element.dataset.category),
  261 |         )).toEqual(['lyrics', 'rhythm', 'mv', 'info'])
  262 | 
  263 |         await page.reload()
  264 |         await expect.poll(async () => page.locator('.song-detail-category-list > .song-detail-category').evaluateAll(
  265 |             (elements) => elements.map((element) => element.dataset.category),
  266 |         )).toEqual(['lyrics', 'rhythm', 'mv', 'info'])
  267 |     })
  268 | })
  269 | 
```