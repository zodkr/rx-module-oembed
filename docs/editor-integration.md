# 다른 에디터 통합 매뉴얼

oEmbed 모듈은 CKEditor 4 만을 대상으로 paste 훅 JS 를 기본 제공합니다(`tpl/js/_ckeditor.js`).
다른 에디터(Draft.js, Quill, TinyMCE, ProseMirror 등) 에서는 이 문서를 참고해
직접 통합하면 됩니다. 핵심은 단 두 가지입니다.

1. 사용자가 URL 을 붙여넣으면 `procOembedFetch` 액션을 호출해 변환 결과를 받습니다.
2. 응답에 담긴 `wrapped_html` 을 에디터가 안전한 방법으로 본문에 삽입합니다.

`wrapped_html` 은 paste 시점의 임베드/카드 마크업을 포함하고 있고 본문에 그대로
저장 · 출력됩니다. 서버 측 후처리(예: transHTML 같은 EditorHandler)는 사용하지
않으므로 통합 측에서 출력 변환을 따로 구현할 필요도 없고, 반대로 에디터 sanitizer
가 내부의 `<iframe>` / 카드 노드를 떨어뜨리지 않도록 주의해야 합니다.

## 1. API 시그니처

### 요청

```
POST /index.php?module=oembed&act=procOembedFetch
Content-Type: application/x-www-form-urlencoded
X-Requested-With: XMLHttpRequest
X-CSRF-Token: <Rhymix CSRF 토큰>

url=<붙여넣은 URL>
&_rx_csrf_token=<토큰>
&width=640    (선택)
&height=360   (선택)
```

`url` 은 **단독으로 들어온 URL** 만 보내세요. 본문 안에 섞인 URL 을 자동으로 잘라
보내면 사용자가 의도한 컨텍스트를 잃을 수 있습니다.

### 응답

```json
// 1) provider 매칭 성공 → iframe/blockquote 임베드
{
  "kind": "embed",
  "wrapped_html": "<div editor_component=\"oembed\" data-kind=\"embed\" data-url=\"...\" data-provider=\"Youtube\" data-width=\"640\" data-height=\"360\"><iframe ...></iframe></div>",
  "url": "https://www.youtube.com/watch?v=...",
  "provider": "Youtube"
}

// 2) provider 매칭 실패 + OG 메타 발견 → 미리보기 카드
{
  "kind": "card",
  "wrapped_html": "<div editor_component=\"oembed\" data-kind=\"card\" data-url=\"...\"><div class=\"preview_card_wrapper\" contenteditable=\"false\">...</div></div>",
  "url": "https://example.com/article"
}

// 3) 둘 다 실패
{ "kind": "fail" }
```

## 2. 통합 시 권장 흐름

```
사용자 paste 이벤트
    ↓
URL 단독 여부 확인 (정규식)
    ↓
sessionStorage 에 실패 호스트 캐시 hit 면 종료
    ↓
placeholder 노드 삽입 (예: <p data-oembed-pending="1"><a href="URL">URL</a></p>)
    ↓
fetch('/index.php?module=oembed&act=procOembedFetch', { ... })
    ↓
응답 kind 가 'embed' 또는 'card' 이고 wrapped_html 이 있으면 placeholder 교체
실패면 호스트를 sessionStorage 에 기록하고 placeholder 의 data-* 속성만 제거
```

placeholder 패턴을 쓰는 이유는 fetch 가 비동기이기 때문입니다. paste 시점에
즉시 텍스트 링크라도 보여줘야 사용자가 빈 화면을 보지 않습니다.

## 3. 마크업을 본문에 삽입할 때

`wrapped_html` 은 **신뢰 가능한 서버 출력** 이지만, 그래도 에디터가 제공하는
안전한 삽입 API 를 사용하는 것을 권장합니다.

| 에디터 | 안전한 삽입 방식 |
| --- | --- |
| **CKEditor 4** | `CKEDITOR.dom.element.createFromHtml(html, editor.document)` 후 `replace(node)` |
| **CKEditor 5** | `editor.model.change(...)` 안에서 `viewToModelClipboard` + `editor.model.insertContent(...)` |
| **TinyMCE** | `editor.insertContent(html)` |
| **Quill** | `quill.clipboard.dangerouslyPasteHTML(index, html)` (이름과 달리 quill 자체 sanitizer 통과) |
| **Draft.js** | `convertFromHTML` → `Modifier.replaceWithFragment` |
| **ProseMirror** | `DOMParser.fromSchema(schema).parseSlice(...)` 후 `tr.replaceSelectionWith(...)` |

`innerHTML = ...` 로 직접 주입하는 것은 가급적 피하세요. 에디터별 sanitizer 와
스키마를 우회하면 클래스/속성이 의도와 다르게 떨어져 나갈 수 있습니다.

## 4. 본문 저장과 출력

저장 시 본문에는 `wrapped_html` 이 통째로(`<div editor_component="oembed" data-...>`
와 그 안의 `<iframe>` / 카드 마크업까지) 남고, 출력 시에도 같은 마크업이 그대로
나갑니다. 따라서 통합 코드 측에서는:

- 저장 전 본문에서 oembed 마크업을 *지우거나 변형하지 마세요* — 특히 sanitizer 가
  자식 노드(`<iframe>` 등)를 떨어뜨리면 출력에서 빈 div 만 남습니다.
- 출력 변환 로직을 따로 구현할 필요가 없습니다 — 별도 후처리가 없습니다.

대신 한 가지 한계가 있습니다: Provider 의 `buildEmbed` 결과나 카드 템플릿이
나중에 바뀌어도 **이미 작성된 글에는 소급 반영되지 않습니다**. 본문을 다시
저장해야 새 마크업으로 교체됩니다.

## 5. CSRF 토큰

Rhymix 는 모든 POST 액션에 CSRF 토큰을 요구합니다. 토큰은 다음 위치 중 하나에서
얻을 수 있습니다.

```js
// 옵션 A: 전역 변수 (Rhymix 가 layout 에 자동 출력)
const token = window.rx_csrf_token;

// 옵션 B: 메타 태그
const token = document.querySelector('meta[name="csrf-token"]')?.content;
```

요청 시 `X-CSRF-Token` 헤더와 `_rx_csrf_token` 폼 필드 둘 다 보내면 가장 안전합니다.

## 6. 실패 호스트 캐시

OG 메타가 전혀 없는 페이지는 두 번째 시도도 똑같이 실패합니다. 사용자 경험을
위해 첫 실패 시 호스트를 `sessionStorage` 에 기록하고, 같은 호스트에 대해서는
즉시 일반 링크로만 두는 것을 권장합니다.

```js
const FAILED_KEY = 'oembed:failed_hosts';
const failed = JSON.parse(sessionStorage.getItem(FAILED_KEY) || '{}');
const host = new URL(url).host;
if (failed[host]) return;  // 즉시 종료
// fetch 실패 시
failed[host] = Date.now();
sessionStorage.setItem(FAILED_KEY, JSON.stringify(failed));
```

브라우저 세션 단위라서 새로고침 시 다시 시도되며, 운영자가 어드민에서 캐시를
강제로 비우게 할 필요가 없습니다.

## 7. 참고: 기본 CKEditor 4 통합 코드

전체 구현은 `tpl/js/_ckeditor.js` 를 참고하세요. 약 170줄짜리 단일 파일이며,
다른 에디터로 옮길 때 가장 좋은 출발점이 됩니다.
