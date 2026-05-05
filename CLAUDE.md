# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

> The global rules in the `CLAUDE.md` at the Rhymix framework root (Module Loading Pattern, PHP Writing Rules, CSRF, Cache Key conventions, etc.) apply as-is. This document only covers the flows and constraints specific to the oembed module.

## Completion Rules

When a user-requested task is finished, commit and push the changes. The following must be observed:

- Before committing, run `git status` / `git diff` to verify the change scope. If files outside the requested scope are mixed in, notify the user first.
- Options that bypass safety checks (`--no-verify`, `--no-gpg-sign`, force push, etc.) are only used when the user explicitly requests them.
- Never auto-perform a force push to protected branches (`dev`, `main`) — always confirm with the user first.
- If the user has asked to hold/review the work, or if the work is incomplete, defer the push.

## Module Identity

A PSR-4 module (`Rhymix\Modules\Oembed\*`) that converts URLs into **iframe embeds** or **OG preview cards** via the CKEditor 4 paste hook and the `procOembedFetch` action. It replaces the legacy `preview` module; when `compatible_mode` is enabled, legacy preview-era markup (`preview_card_*`, `media_embed_wrapper`, `.instagram-media`, `.fb-post`, `.twitter-tweet`) continues to render correctly on view pages. Bundled providers: YouTube, Facebook, Instagram, X (Twitter). The legacy Imgur provider was removed because Imgur's embed CDN doesn't send CORS headers reliably and we no longer want to ship a provider that needs special-cased crossorigin handling — old `imgur-embed-pub` markup in archived posts will no longer auto-load embed.js.

> ⚠️ Must not be activated alongside the `preview` module. `Config::isPreviewModuleActive()` checks for the existence of `modules/preview/conf/info.xml` and the admin screen surfaces a conflict badge.

## Core Data Flow (paste → stored body → output)

1. **Paste (browser)** — `tpl/js/_ckeditor.js`
   - Only standalone URLs are extracted and immediately replaced with a `<p data-oembed-pending="1">` placeholder.
   - `oembed:failed_hosts` in `sessionStorage` (1-hour TTL) blocks re-attempts for the same host.
   - When calling `procOembedFetch`, the CSRF token is sent via both header (`X-CSRF-Token`) and form field (`_rx_csrf_token`).
   - On response, the placeholder is swapped via `CKEDITOR.dom.element.createFromHtml` — **never inject via `innerHTML` directly** (that bypasses the CKEditor sanitizer).

2. **Conversion (server)** — `controllers/Controller.php::procOembedFetch`
   - `RemoteFetcher::normalizeUrl` → `Registry::match` is attempted → on a hit, `provider->fetchInfo($url)` (옵션 hook) 가 호출돼 oEmbed 같은 외부 메타에서 `width / height` (iframe 비율 보정용) 와 `thumbnail_url` 을 받아온다. `thumbnail_url` 이 있으면 OG 카드와 동일한 `ImageAttacher` 흐름으로 첨부 등록되고, 임베드 wrapper 에도 `data-oembed-file-srl` 와 응답의 `upload_target_srl` / `file_srl` 가 동일 패턴으로 실린다. 그 다음 `provider->buildEmbed()` output 이 **wrapped in `<div editor_component="oembed" data-url="…">…</div>`** 으로 감싸져 반환된다.
   - On miss: `RemoteFetcher::fetchHtml` → `OpenGraph::parse` → `ImageAttacher::attach` (downloads OG image, stages the temp file under `RX_BASEDIR/files/attach/chunks/` so file.controller.php:1061 routes it through the chunked-upload move path, then calls `FileController::insertFile($fileInfo, $moduleSrl, $uploadTargetSrl, 0, false, $editorSequence)` — `manual_insert=false` so the board's allowed-extension / size / total-attach-size policies and `adjustUploadedImage` (EXIF rotate, board-level format conversion) all apply; policy violations throw `msg_not_allowed_filetype` / `msg_exceeds_limit_size` which are caught and trigger the og.image URL fallback. On save the file is linked to the new `document_srl` like any other attachment) → `CardRenderer::render` → wrapped in the same `<div editor_component="oembed" data-url="…">` shape. The response also carries `upload_target_srl` (for the JS to sync into the form's `document_srl` hidden field — same pattern as `procFileUpload`) and `file_srl` (so the JS can call `procFileDelete` to claw back the attachment if the placeholder is gone before the response lands, e.g. user undid the paste — without this, a stale response would orphan-attach an image the body never references).
   - Response shape: `{ kind: 'embed' | 'card' | 'fail', wrapped_html, url, provider? }`.
   - The wrapper carries: `editor_component="oembed"` (CKEditor widget hint), `contenteditable="false"` (single-block selection inside the editor), `data-url` (debugging/tooling reference), `data-oembed-type` (one of `social` / `multimedia` / `card` — used by style.css for type-specific visualization), and on provider-matched embeds also `data-oembed-provider` (class basename like `Instagram`/`Facebook`/`X`/`Youtube` — used by style.css to pick the right label/badge). 임베드 차원/카드 메타 등은 모두 inner HTML 에 들어 있으므로 wrapper 에 중복 보관하지 않는다.

2.5. **Orphan attachment cleanup** — `EventHandlers::pruneOrphanedOembedFiles` 가 `document.insertDocument` / `document.updateDocument` / `comment.insertComment` / `comment.updateComment` 의 **after**-trigger 에 묶여 있다. 본문에서 `data-oembed-file-srl="N"` 들을 모아 참조 set 을 만들고, 같은 `upload_target_srl` 에 묶인 파일 중 `source_filename` 이 ImageAttacher 가 발급한 `oembed_<16hex>.<ext>` 패턴인 것을 oembed 첨부 후보로 골라낸다. 참조 set 에 없는 후보는 `deleteFile` 로 회수. after 위치를 쓰는 이유: before 시점은 본문 검증·보안 검사·extra_vars 업로드가 진행되기 전이라 그 단계가 throw 하면 첨부 물리 파일만 삭제된 채 문서는 롤백돼 기존 카드 이미지가 깨진다 (`Storage::delete` 는 비트랜잭셔널). after 는 setFilesValid 직후·`$oDB->commit()` 직전이라 거의 모든 실패 경로를 통과한 뒤다. 식별자로 `upload_target_type` 컬럼을 쓰지 않는 이유: setFilesValid 가 매 저장마다 그 컬럼을 'doc' 로 덮어써서 재편집 시점에는 oembed 표식이 사라진다. `source_filename` 은 어떤 흐름에서도 변경되지 않아 신규/N회 재편집 모두에서 일관 매칭된다. 일반 업로드 첨부는 이 패턴과 충돌할 확률이 사실상 0 (16자리 16진수). 사용자가 source-edit 으로 `data-oembed-file-srl` 속성을 임의로 지우면 그 첨부는 함께 정리된다 (속성이 깨진 카드는 본문에서도 동작하지 않으므로 일관성).

3. **Output (body render)** — paste 시점에 만들어진 `wrapped_html` 이 **본문에 그대로 박제되어 그대로 출력**된다. Rhymix 에디터 코어가 `<div editor_component="oembed">` 를 후처리하지 않으므로(이 모듈은 별도 EditorHandler 를 등록하지 않는다), Provider 의 `buildEmbed` 결과가 바뀌어도 **기존 글에는 소급 반영되지 않는다**. 변경을 게시물에 반영하려면 본문을 다시 저장해야 한다. 단, **외부 SDK `<script>` 만은 예외** — `_render.js` 가 `DOMContentLoaded` 시점에 본문 DOM 을 검사해 provider 의 `getEmbedAssets()` selector 와 매칭되면 SDK 를 `document.head` 에 동적 주입한다. SDK URL 이 바뀌어도 본문 markup 은 그대로 둔 채 즉시 반영된다(본문엔 selector 가 가리키는 CSS 클래스만 박제돼 있으면 된다).

## Provider Extension Contract

Just drop a single file at `providers/{Name}.php` — `Registry::scanProviderClasses()` discovers it via `glob`. After adding a new file, click admin → oEmbed → Provider Management → **Refresh Provider Cache** (= `Registry::flush()`), or recompile the Rhymix cache.

```php
namespace Rhymix\Modules\Oembed\Providers;
use Rhymix\Modules\Oembed\Models\Provider;

class Vimeo extends Provider {
  public string $name = 'Vimeo';
  public string $type = self::TYPE_MULTIMEDIA;       // or TYPE_SOCIAL — controls 16:9 vs 4:3 default when dimensions are unset
  public array $hosts = ['vimeo.com', 'player.vimeo.com'];
  public array $patterns = [
    '~(?:https?:)?//(?:www\.|player\.)?vimeo\.com/(\d+)~i' => ['video_id'],
  ];
  public function buildEmbed(array $matchData, ?int $width = null, ?int $height = null): string { … }
}
```

Rules:
- **Use `~` as the PCRE delimiter.** URLs frequently contain `#` and `?`, which collide with the `#` delimiter (Youtube once broke for this exact reason — commit `237e542`). Standardize new patterns on `~` as well.
- The values in `$patterns` are **arrays of capture-group names** — the base `Provider::match()` maps them into `$captures[name]`.
- Pattern priority is "first match wins" in registration order. Put narrower patterns first and the most permissive single pattern last — X uses this to keep `/i/web/status/...` from being captured as `username=web`.
- Escape all user input via `htmlspecialchars(…, ENT_QUOTES, 'UTF-8')`. Use `rawurlencode` for URL path segments.
- **Override `fetchInfo()` when the provider exposes an oEmbed / metadata endpoint.** 기본 구현은 `null` 을 돌려주고 Controller 는 클라이언트가 보낸 dimension 만 가지고 `getDimensions()` 의 type 별 기본 비율로 폴백한다. YouTube 처럼 공식 oEmbed (`https://www.youtube.com/oembed?url=…&format=json`) 가 영상마다 다른 정확한 `width / height / thumbnail_url` 을 돌려주는 경우, 이 hook 으로 응답을 받아오면 (a) iframe 비율이 영상 본연의 비율(Shorts 9:16, 21:9 시네마틱 등) 을 정확히 따르고, (b) 섬네일이 ImageAttacher 를 통해 본문 첨부로 자동 등록돼 게시판 자동 섬네일 / 첨부 목록 / RSS 미리보기에 잡힌다. 반환 키는 모두 optional (`width`, `height`, `thumbnail_url`) — 일부만 채워 돌려줘도 호출자가 폴백 처리. 외부 호출은 반드시 `RemoteFetcher` (JSON 응답이면 `fetchJson`, HTML 이면 `fetchHtml`) 를 거쳐 SSRF 가드 / timeout / size cap / content-type 가드를 통과시켜야 한다. `fetchHtml` 은 `text/html` / `application/xhtml` 만 허용하므로 oEmbed JSON 을 받을 땐 반드시 `fetchJson` 을 써야 한다 — `fetchHtml` 로 호출하면 content-type 가드에서 항상 null 로 떨어진다. 네트워크/파싱 실패는 `null` 반환으로 흡수하면 자동으로 기본 비율 폴백.
- **Never put `<script>` inside `buildEmbed()` output.** The wrapped HTML is stored in the document body and HTMLPurifier strips `<script>` at save time — the post can fail to save outright. If your provider needs an external SDK (Instagram embed.js, Facebook sdk.js, X widgets.js, etc.) to activate the markup, override `getEmbedAssets()` instead. It returns a list of `['selector' => '<css selector>', 'script' => '<https url>', 'crossorigin' => bool, 'normalize' => [...]]`. The selector is passed to client-side `document.querySelector` — when at least one matching node exists in the rendered DOM, `_render.js` injects a `<script>` for that URL into `document.head` (deduplicated by URL). Selectors can target multiple classes in one entry (Facebook uses `.fb-post, .fb-video` for a single SDK URL). Detection runs on `DOMContentLoaded`, so it sees the final DOM after all module/addon/sanitizer post-processing — this is more robust than scanning the rendered HTML server-side, where addon execution order vs `before display` would matter. Set `crossorigin => true` only when the SDK's CDN sends CORS headers AND the official snippet uses `crossorigin="anonymous"` (Facebook does, Instagram/X do not). Forcing anonymous CORS mode on a CDN that doesn't allow it makes the browser block the script load. The optional `normalize` field is a list of `{detect, addClass}` rules that run before the SDK detection pass — Instagram uses `[{detect: 'blockquote[data-instgrm-permalink]:not(.instagram-media)', addClass: 'instagram-media'}]` and X uses `data-oembed-tweet-id` as its anchor, because CKEditor ACF / HTMLPurifier commonly drop the SDK-required class while leaving `data-*` attributes intact. Use a detect selector that survives sanitizers (custom data attributes are usually safe; class allowlists are not).

## SSRF / Outbound Call Guard

External calls **must go through `Models\RemoteFetcher`**. Direct `HTTP::get` / `file_get_contents` is forbidden.

- `RemoteFetcher::isUrlSafe()` — http/https only; blocks localhost / `*.localhost` / RFC1918 / link-local / `169.254.169.254` (AWS metadata) / `fd00:ec2::254`. The host is resolved via `dns_get_record(A|AAAA)` and every returned IP must be public.
- The `on_redirect` callback re-runs the same check on every hop — the check cannot be bypassed.
- Limits: 3-second timeout, 5 redirects max, HTML 2 MB (truncated when exceeded), images 5 MB (rejected when exceeded — returns `null`), URL 2048 chars.
- All failures return `null`. Callers fall back gracefully.

## iframe Whitelist Policy

**Do not auto-register hosts.** Per site security policy, the operator must approve hosts manually at System → Settings → Security → External Multimedia Allow (`Rhymix\Framework\Filters\MediaFilter`) for the iframe to survive on the rendered page. The admin Provider screen uses `MediaFilter::matchWhitelist()` to show per-host registration status as badges and surfaces a summary of unregistered hosts. Do not reintroduce auto-whitelisting code (it was intentionally removed in v0.4.0).

The hosts the admin screen displays come from **`Provider::getEmbedHosts()`**, not `$hosts`. The two are deliberately separated:

- `$hosts` — paste 시점에 입력 URL 의 호스트와 매칭하는 후보 (paste detection only).
- `getEmbedHosts()` — 본문에 박힐 iframe / SDK src 의 실제 호스트. MediaFilter 화이트리스트에 등록돼야 출력 단계에서 살아남는 호스트들. 단순 iframe provider (Youtube 처럼 입력 도메인과 임베드 도메인이 동일) 는 override 안 해도 되고, 기본 구현이 `$hosts` 를 그대로 반환한다.

이 분리 전, X / Reddit 등 SDK 변환형 provider 는 SDK 호스트를 `$hosts` 에 섞어 넣는 워크어라운드를 썼는데, paste 매칭 의미가 흐려져 v0.5.0 에서 분리했다.

## EventHandlers Routing Subtleties

`controllers/EventHandlers.php::injectEditorAssets` injects the paste hook / card assets on the `before display` trigger. Two pitfalls:

1. **`act` fallback is required** — When the URL has no `act`, Rhymix `ModuleHandler` routes to the module's `default_index_act` but does not populate that value into `Context::get('act')` (`classes/module/ModuleHandler.class.php:355-358`). So a view request that arrives with only `mid=board` won't match `dispBoardContent`. Use `current_module_info` + `ModuleModel::getModuleActionXml` to fall back to `default_index_act` / `admin_index_act` (commit `9bf9521`).
2. **Editor asset injection branching** — `WRITE_ACTS` (write document/comment) checks via `EditorModel::getEditorConfig` whether the editor is ckeditor, then injects `_ckeditor.js` + adds `tpl/css/style.css` via `Context::addCssFile` (for the host page chrome and CKEditor's contentsCss propagation), and *additionally* reads `tpl/css/style.css` content fresh on every request and emits it as inline `window.oembedEditorCss`. `_render.js` is *not* loaded on write pages. The reason the editor-scoped portion ships inline rather than only via `Context::addCssFile`: Rhymix stamps the URL with `?t=<minified mtime>` for cache busting, but the minified copy's mtime can stay stale across deploys, and CKEditor 4 captures that stale URL in `contentsCss` at editor init — meaning the iframe loads an outdated CSS forever. `_ckeditor.js` calls `CKEDITOR.addCss(window.oembedEditorCss)` for new instances and directly inserts a `<style>` into existing instances' documents (covers iframe and divarea modes uniformly). `VIEW_ACTS` (read document + admin declared/review screens — commit `f7211f7`) adds `style.css` plus `_render.js` together with an inline `window.oembedEmbedAssets` payload (see Provider Extension Contract). The editor-only rules in `style.css` are scoped to `.rhymix_content.cke_editable, .cke_wysiwyg_div`, so loading the same file on view pages is harmless — the scoped selectors don't match outside the editor. `_render.js` does the actual DOM scan + SDK injection on `DOMContentLoaded`; in compatible mode it additionally activates legacy `iframe[data-src]` placeholders (`window.oembedCompatibleMode`). If neither any provider declares assets nor compatible mode is on, `_render.js` is not loaded. If you remove the admin declared screens from `VIEW_ACTS`, body cards and provider SDK injection both break.

## Config / Skin

- Module config lives in `Models\Config` (static cache + `ModuleModel::getModuleConfig('oembed')`). Keys: `compatible_mode` (Y/N), `skin` (directory name), `enabled_providers` (array — whitelist).
- 신규 설치 시 `Install::moduleInstall()` 가 `Config::BUNDLED_PROVIDERS` (`Youtube`, `Facebook`, `Instagram`, `X`, `Reddit`) 만 `enabled_providers` 에 시드한다. 기존에 `disabled_providers` 만 가진 사이트는 `Install::moduleUpdate()` 가 `enabled_providers = allKeys − disabled_providers` 로 1회 변환하고 `disabled_providers` 키를 제거한다.
- Skins live at `skins/{name}/card.blade.php` + `card.css` + `skin.xml`. `Config::getSkin()` falls back to `default` when the directory is missing.
- `CardRenderer::render()` does `\Context::set('oembed_card', …)` then `TemplateHandler::compile($skinPath, 'card')` — it compiles whichever exists, Blade or the XE template (`card.*`).

## Admin Form Data Model (a once-confusing point)

`procOembedAdminInsertConfig` handles both screens (`dispOembedAdminConfig` / `dispOembedAdminProviders`) under a single action. 두 폼은 hidden `<input name="screen" value="config|providers">` 로 자기 출처를 식별하고, 핸들러는 `Context::get('screen')` 으로 분기한다. (과거에는 `Context::get('act')` 로 분기했는데 그 시점의 `act` 는 핸들러 자기 이름이라 어떤 분기도 참이 되지 않아 설정이 무시되는 버그가 있었다 — issue #4.)

**Provider 화면은 enabled list 를 화이트리스트로 직접 저장한다** (`config->enabled_providers`). 즉 폼에서 체크된 provider 만 paste 매칭 대상이 된다. `providers/` 디렉터리에 새 파일을 떨어뜨려도 운영자가 어드민에서 명시적으로 활성화하지 않으면 동작하지 않는다 — 의도된 보안 정책이다 (issue #3). Registry 쪽 적용은 `Models\Registry::filterEnabled()` — `enabled_providers` 가 비어 있으면 `[]` 을 반환해 paste 매칭이 모두 fail.

## Build / Test

This module has no separate build/test scripts. It does not use `vendor/` or `node_modules/` either (both pinned in .gitignore). Verification:
- PHP changes → recompile the Rhymix cache, or admin → oEmbed → Provider Management → Refresh Cache.
- JS/CSS changes → hard reload in the browser. `localStorage.setItem('oembed:debug', '1')` makes `_ckeditor.js` emit console debug logs.
- Reset failed-host cache → `sessionStorage.removeItem('oembed:failed_hosts')`, or open a new tab/session.

## Common Stuck Points

- **Delimiter collisions when editing PCRE patterns** — Standardize on `~`. With `#` you'll silent-miss whenever the URL contains a fragment / query because of `#` and `?`.
- **`failed_hosts` looking like a permanent ban** — TTL is 1 hour (commit `b3b44f8`). To clear immediately, delete the sessionStorage entry.
- **iframe clicks not working inside the CKEditor area** — Intentional. The editor-only rules in `tpl/css/style.css` block iframe interaction only inside the wysiwyg area (commit `c632b5c`). The selectors are scoped to `.rhymix_content.cke_editable, .cke_wysiwyg_div`, so they don't apply on the read page and playback there works normally.
- **Assets not injected because `act` is empty** — Make sure the `default_index_act` fallback in `EventHandlers::injectEditorAssets` is always traversed (commit `9bf9521`).
- **Body shows a bare `<div>` instead of a card/embed** — paste 시점의 `wrapped_html` 이 그대로 박제되므로 `<iframe>` 또는 카드 마크업이 본문에 같이 저장되어 있어야 한다. 본문을 외부에서 가공하거나 sanitizer 가 자식 노드를 떨어뜨리면 빈 div 만 남는다. 또한 iframe 의 호스트가 시스템 → 보안 → 외부 멀티미디어 허용에 등록되지 않았다면 `MediaFilter` 가 출력 단계에서 iframe 을 제거한다.
- **Instagram/Facebook/X 임베드가 본문에선 보이지만 활성화되지 않는다** — 브라우저 DevTools → Network 에서 `_render.js` 가 로드됐는지, `<head>` 에 `data-oembed-sdk="..."` 속성을 가진 `<script>` 가 동적 추가됐는지 확인. Provider 의 `getEmbedAssets()` 에서 반환한 selector 와 본문 DOM 이 매칭돼야 SDK 가 주입된다. 본문 sanitizer 가 해당 클래스를 제거하면 selector 가 매칭되지 않아 SDK 도 누락된다 — 이 경우 `normalize` 규칙이 살아남는 data 속성을 anchor 로 클래스를 복원해야 활성화된다. 과거에는 SDK 가 `buildEmbed()` 안의 `<script>` 로 본문에 함께 저장됐는데, HTMLPurifier 가 이를 제거해 글 저장 자체가 실패하던 회귀 버그가 있었다 — 다시 본문에 `<script>` 를 넣지 말 것.

## Other Editor Integrations

The integration guide for editors other than CKEditor 4 lives in `docs/editor-integration.md`. The essentials: call `procOembedFetch`, then insert `wrapped_html` into the body via the editor's safe-insertion API. The wrapped markup is stored as-is and rendered as-is — no server-side post-processing happens — so the integration side just needs to make sure the editor's sanitizer does not strip the inner `<iframe>` / card nodes.
