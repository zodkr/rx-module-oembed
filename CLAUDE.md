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

A PSR-4 module (`Rhymix\Modules\Oembed\*`) that converts URLs into **iframe embeds** or **OG preview cards** via the CKEditor 4 paste hook and the `procOembedFetch` action. It replaces the legacy `preview` module; when `compatible_mode` is enabled, legacy preview-era markup (`preview_card_*`, `media_embed_wrapper`, `.instagram-media`, `.fb-post`, `.imgur-embed-pub`) continues to render correctly on view pages.

> ⚠️ Must not be activated alongside the `preview` module. `Config::isPreviewModuleActive()` checks for the existence of `modules/preview/conf/info.xml` and the admin screen surfaces a conflict badge.

## Core Data Flow (paste → stored body → output)

1. **Paste (browser)** — `tpl/js/_ckeditor.js`
   - Only standalone URLs are extracted and immediately replaced with a `<p data-oembed-pending="1">` placeholder.
   - `oembed:failed_hosts` in `sessionStorage` (1-hour TTL) blocks re-attempts for the same host.
   - When calling `procOembedFetch`, the CSRF token is sent via both header (`X-CSRF-Token`) and form field (`_rx_csrf_token`).
   - On response, the placeholder is swapped via `CKEDITOR.dom.element.createFromHtml` — **never inject via `innerHTML` directly** (that bypasses the CKEditor sanitizer).

2. **Conversion (server)** — `controllers/Controller.php::procOembedFetch`
   - `RemoteFetcher::normalizeUrl` → `Registry::match` is attempted → on a hit, the `provider->buildEmbed()` output is **wrapped in `<div editor_component="oembed" data-url="…">…</div>`** and returned.
   - On miss: `RemoteFetcher::fetchHtml` → `OpenGraph::parse` → `ImageAttacher::attach` (cache OG image locally) → `CardRenderer::render` → wrapped in the same `<div editor_component="oembed" data-url="…">` shape.
   - Response shape: `{ kind: 'embed' | 'card' | 'fail', wrapped_html, url, provider? }`.
   - The wrapper carries only `editor_component="oembed"` (so CKEditor recognizes the block as a single non-editable widget) and `data-url` (debugging/tooling reference). 임베드 차원/카드 메타 등은 모두 inner HTML 안에 이미 들어 있으므로 wrapper 에 중복 보관하지 않는다.

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
- **Use `~` as the PCRE delimiter.** URLs frequently contain `#` and `?`, which collide with the `#` delimiter (Youtube/Imgur once broke for this exact reason — recent commit `237e542`). Standardize new patterns on `~` as well.
- The values in `$patterns` are **arrays of capture-group names** — the base `Provider::match()` maps them into `$captures[name]`.
- Pattern priority is "first match wins" in registration order. Put narrower patterns (album / gifv etc.) first and the most permissive single pattern last (see Imgur).
- Escape all user input via `htmlspecialchars(…, ENT_QUOTES, 'UTF-8')`. Use `rawurlencode` for URL path segments.
- **Never put `<script>` inside `buildEmbed()` output.** The wrapped HTML is stored in the document body and HTMLPurifier strips `<script>` at save time — the post can fail to save outright. If your provider needs an external SDK (Instagram embed.js, Facebook sdk.js, Imgur embed.js, etc.) to activate the markup, override `getEmbedAssets()` instead. It returns a list of `['selector' => '<css selector>', 'script' => '<https url>', 'crossorigin' => bool]`. The selector is passed to client-side `document.querySelector` — when at least one matching node exists in the rendered DOM, `_render.js` injects a `<script>` for that URL into `document.head` (deduplicated by URL). Selectors can target multiple classes in one entry (Facebook uses `.fb-post, .fb-video` for a single SDK URL). Detection runs on `DOMContentLoaded`, so it sees the final DOM after all module/addon/sanitizer post-processing — this is more robust than scanning the rendered HTML server-side, where addon execution order vs `before display` would matter. Set `crossorigin => true` only when the SDK's CDN sends CORS headers AND the official snippet uses `crossorigin="anonymous"` (Facebook does, Instagram/Imgur do not). Forcing anonymous CORS mode on a CDN that doesn't allow it makes the browser block the script load — Imgur's `s.imgur.com/min/embed.js` is the canonical example.

## SSRF / Outbound Call Guard

External calls **must go through `Models\RemoteFetcher`**. Direct `HTTP::get` / `file_get_contents` is forbidden.

- `RemoteFetcher::isUrlSafe()` — http/https only; blocks localhost / `*.localhost` / RFC1918 / link-local / `169.254.169.254` (AWS metadata) / `fd00:ec2::254`. The host is resolved via `dns_get_record(A|AAAA)` and every returned IP must be public.
- The `on_redirect` callback re-runs the same check on every hop — the check cannot be bypassed.
- Limits: 3-second timeout, 5 redirects max, HTML 2 MB (truncated when exceeded), images 5 MB (rejected when exceeded — returns `null`), URL 2048 chars.
- All failures return `null`. Callers fall back gracefully.

## iframe Whitelist Policy

**Do not auto-register hosts.** Per site security policy, the operator must approve hosts manually at System → Settings → Security → External Multimedia Allow (`Rhymix\Framework\Filters\MediaFilter`) for the iframe to survive on the rendered page. The admin Provider screen uses `MediaFilter::matchWhitelist()` to show per-host registration status as badges and surfaces a summary of unregistered hosts. Do not reintroduce auto-whitelisting code (it was intentionally removed in v0.4.0).

## EventHandlers Routing Subtleties

`controllers/EventHandlers.php::injectEditorAssets` injects the paste hook / card assets on the `before display` trigger. Two pitfalls:

1. **`act` fallback is required** — When the URL has no `act`, Rhymix `ModuleHandler` routes to the module's `default_index_act` but does not populate that value into `Context::get('act')` (`classes/module/ModuleHandler.class.php:355-358`). So a view request that arrives with only `mid=board` won't match `dispBoardContent`. Use `current_module_info` + `ModuleModel::getModuleActionXml` to fall back to `default_index_act` / `admin_index_act` (commit `9bf9521`).
2. **Editor asset injection branching** — `WRITE_ACTS` (write document/comment) checks via `EditorModel::getEditorConfig` whether the editor is ckeditor, then injects `_ckeditor.js` + `editor.css`. `VIEW_ACTS` (read document + admin declared/review screens — commit `f7211f7`) adds `card.css` plus `_render.js` together with an inline `window.oembedEmbedAssets` payload (see Provider Extension Contract). `_render.js` does the actual DOM scan + SDK injection on `DOMContentLoaded`; in compatible mode it additionally activates legacy `iframe[data-src]` placeholders (`window.oembedCompatibleMode`). If neither any provider declares assets nor compatible mode is on, `_render.js` is not loaded. If you remove the admin declared screens from `VIEW_ACTS`, body cards and provider SDK injection both break.

## Config / Skin

- Module config lives in `Models\Config` (static cache + `ModuleModel::getModuleConfig('oembed')`). Keys: `compatible_mode` (Y/N), `skin` (directory name), `disabled_providers` (array).
- Skins live at `skins/{name}/card.blade.php` + `card.css` + `skin.xml`. `Config::getSkin()` falls back to `default` when the directory is missing.
- `CardRenderer::render()` does `\Context::set('oembed_card', …)` then `TemplateHandler::compile($skinPath, 'card')` — it compiles whichever exists, Blade or the XE template (`card.*`).

## Admin Form Data Model (a once-confusing point)

`procOembedAdminInsertConfig` handles both screens (`dispOembedAdminConfig` / `dispOembedAdminProviders`) under a single action. **The Provider form only sends the enabled list and we derive disabled by inverse** — `array_diff(allKeys, enabled)`. Newly added Providers default to enabled. If you change this to "store enabled directly", new Providers will silently default to disabled — that's a regression.

## Build / Test

This module has no separate build/test scripts. It does not use `vendor/` or `node_modules/` either (both pinned in .gitignore). Verification:
- PHP changes → recompile the Rhymix cache, or admin → oEmbed → Provider Management → Refresh Cache.
- JS/CSS changes → hard reload in the browser. `localStorage.setItem('oembed:debug', '1')` makes `_ckeditor.js` emit console debug logs.
- Reset failed-host cache → `sessionStorage.removeItem('oembed:failed_hosts')`, or open a new tab/session.

## Common Stuck Points

- **Delimiter collisions when editing PCRE patterns** — Standardize on `~`. With `#` you'll silent-miss whenever the URL contains a fragment / query because of `#` and `?`.
- **`failed_hosts` looking like a permanent ban** — TTL is 1 hour (commit `b3b44f8`). To clear immediately, delete the sessionStorage entry.
- **iframe clicks not working inside the CKEditor area** — Intentional. `tpl/css/editor.css` blocks iframe interaction only inside the wysiwyg area (commit `c632b5c`). It is not injected on the read page, so playback there works normally.
- **Assets not injected because `act` is empty** — Make sure the `default_index_act` fallback in `EventHandlers::injectEditorAssets` is always traversed (commit `9bf9521`).
- **Body shows a bare `<div>` instead of a card/embed** — paste 시점의 `wrapped_html` 이 그대로 박제되므로 `<iframe>` 또는 카드 마크업이 본문에 같이 저장되어 있어야 한다. 본문을 외부에서 가공하거나 sanitizer 가 자식 노드를 떨어뜨리면 빈 div 만 남는다. 또한 iframe 의 호스트가 시스템 → 보안 → 외부 멀티미디어 허용에 등록되지 않았다면 `MediaFilter` 가 출력 단계에서 iframe 을 제거한다.
- **Instagram/Facebook/Imgur 임베드가 본문에선 보이지만 활성화되지 않는다** — 브라우저 DevTools → Network 에서 `_render.js` 가 로드됐는지, `<head>` 에 `data-oembed-sdk="..."` 속성을 가진 `<script>` 가 동적 추가됐는지 확인. Provider 의 `getEmbedAssets()` 에서 반환한 selector 와 본문 DOM 이 매칭돼야 SDK 가 주입된다. 본문 sanitizer 가 해당 클래스를 제거하면 selector 가 매칭되지 않아 SDK 도 누락된다. 과거에는 SDK 가 `buildEmbed()` 안의 `<script>` 로 본문에 함께 저장됐는데, HTMLPurifier 가 이를 제거해 글 저장 자체가 실패하던 회귀 버그가 있었다 — 다시 본문에 `<script>` 를 넣지 말 것.

## Other Editor Integrations

The integration guide for editors other than CKEditor 4 lives in `docs/editor-integration.md`. The essentials: call `procOembedFetch`, then insert `wrapped_html` into the body via the editor's safe-insertion API. The wrapped markup is stored as-is and rendered as-is — no server-side post-processing happens — so the integration side just needs to make sure the editor's sanitizer does not strip the inner `<iframe>` / card nodes.
