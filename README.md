# rx-module-oembed

Rhymix 용 oEmbed 모듈. CKEditor 4 에서 사용자가 URL 을 붙여넣으면 자동으로
**임베드(iframe/blockquote/oEmbed)** 또는 **Open Graph 미리보기 카드** 로 변환합니다.
기존 `preview` 모듈을 완전히 대체하는 것을 목표로 설계되었으며, 외부 액션 시그니처
(`dispPreviewCard` 등)와 마크업(`preview_card_*`)을 그대로 흡수해 마이그레이션
부담을 최소화합니다.

## 주요 특징

- **PHP 파일 한 개로 새 서비스 추가** — `providers/{Name}.php` 한 파일을 떨어뜨리면
  소스 변경/빌드 없이 즉시 등록됩니다.
- **기본 Provider 4종** — YouTube, Facebook, Instagram, Imgur (Meta 토큰 불필요).
- **Open Graph 카드 자동 생성** — 매칭되는 provider 가 없는 URL 은 OG/Twitter Card
  메타를 추출해 미리보기 카드로 변환합니다.
- **SSRF 가드** — 사설/예약 IP, localhost, 클라우드 메타데이터 엔드포인트
  (169.254.169.254) 차단. 리다이렉트도 검증.
- **iframe whitelist 명시적 승인** — 사이트 보안 정책상 oembed 가 시스템
  설정을 자동 갱신하지 않습니다. 어드민 → oEmbed → Provider 관리 화면에서
  승인이 필요한 호스트 목록을 보여주고, 운영자가 시스템 → 설정 → 보안 →
  외부 멀티미디어 허용에서 직접 등록합니다. 등록 전까지 본문 출력 시
  iframe 이 차단됩니다.
- **preview 호환** — `dispPreviewCard` / `dispPreviewIframe` 등 외부 액션과
  `preview_card_*` 마크업을 그대로 응답해 외부 캐시·이메일 링크가 깨지지 않게 함.

## 설치

이 저장소는 [zodkr/core](https://github.com/zodkr/core) 의 `modules/.gitignore`
에 의해 core 트리와 분리되어 있습니다. `modules/oembed` 위치에 별도 clone 으로
설치합니다.

```sh
cd /path/to/rhymix-site
git clone -b dev https://github.com/zodkr/rx-module-oembed.git modules/oembed
```

설치 후 관리자 화면 → 시스템 → 모듈 관리에서 oEmbed 모듈을 활성화합니다.

> ⚠️ `preview` 모듈과 동시 활성화 금지. 어드민 화면이 활성 충돌을 감지해
> 경고와 마이그레이션 가이드를 표시합니다.

## 새 Provider 추가

`modules/oembed/providers/` 디렉터리에 PHP 파일 하나만 떨어뜨리면 됩니다.

```php
// modules/oembed/providers/Vimeo.php
<?php

namespace Rhymix\Modules\Oembed\Providers;

use Rhymix\Modules\Oembed\Models\Provider;

class Vimeo extends Provider
{
  public string $name = 'Vimeo';
  public string $type = self::TYPE_MULTIMEDIA;
  public bool $oembed = false;
  public array $hosts = ['vimeo.com', 'player.vimeo.com'];
  public array $patterns = [
    '#(?:https?:)?//(?:www\.|player\.)?vimeo\.com/(\d+)#i' => ['video_id'],
  ];

  public function buildEmbed(array $matchData, ?int $width = null, ?int $height = null): string
  {
    $videoId = $matchData['captures']['video_id'] ?? '';
    if ($videoId === '') return '';
    [$w, $h] = $this->getDimensions($width, $height);
    $src = 'https://player.vimeo.com/video/' . rawurlencode($videoId);
    return sprintf(
      '<iframe src="%s" width="%d" height="%d" frameborder="0" allow="autoplay; fullscreen; picture-in-picture" allowfullscreen></iframe>',
      htmlspecialchars($src, ENT_QUOTES, 'UTF-8'), $w, $h
    );
  }
}
```

저장 후 어드민 → oEmbed → Provider 관리 → **Provider 캐시 새로고침** 버튼을
한 번 누르면 즉시 등록됩니다(또는 Rhymix 캐시 재컴파일).

### Provider 인터페이스

| 필드 | 타입 | 설명 |
| --- | --- | --- |
| `$name` | `string` | 어드민에 표시할 이름 |
| `$type` | `'multimedia' \| 'social'` | 미지정 크기 시 16:9 / 4:3 비율 결정 |
| `$oembed` | `bool` | true 면 oEmbed 엔드포인트 호출 (현재 v0.x 에서는 직접 임베드만) |
| `$hosts` | `string[]` | iframe whitelist 자동 등록 대상 |
| `$patterns` | `array<string, string[]>` | PCRE 패턴 → 캡처 그룹 이름 |

`match()` 와 `getDimensions()` 는 베이스 클래스가 제공하므로 일반적으로
`buildEmbed()` 만 구현하면 됩니다.

## 다른 에디터 통합

CKEditor 4 외 에디터(예: Draft.js, Quill, TinyMCE 등)에서도
`procOembedFetch` 액션을 직접 호출해 임베드/카드 마크업을 받아 삽입할 수
있습니다. 자세한 흐름과 예시 코드는
[`docs/editor-integration.md`](docs/editor-integration.md) 를 참고하세요.

## 마일스톤

| 버전 | 범위 |
| --- | --- |
| **v0.1.0** | 모듈 골격, AbstractProvider, Registry, Youtube, procOembedFetch (embed), CKEditor JS, 에디터 컴포넌트, preview 액션 별칭, 어드민 빈 화면 |
| **v0.2.0** | OG 파서, RemoteFetcher (SSRF 가드), CardRenderer, ImageAttacher, 카드 흐름 |
| **v0.3.0** | Facebook / Instagram / Imgur Provider |
| **v0.4.0** | 어드민 UI 강화 (Provider 캐시 새로고침, 호스트 whitelist 상태 배지, 미등록 호스트 종합 안내, preview 비활성화 가이드). iframe whitelist 자동 등록 제거 — 명시적 승인 정책 도입 |
| **v0.5.0** | 외부 컨트리뷰터 가이드, 다른 에디터 통합 매뉴얼 |

## 디렉터리 구조

PSR-4 (`Rhymix\Modules\Oembed\*`).

```
modules/oembed/
├── conf/                      info.xml, module.xml
├── controllers/               Base / View / Controller / Admin / Install / EventHandlers
├── models/                    Config / Provider / Registry / RemoteFetcher /
│                              OpenGraph / CardRenderer / ImageAttacher
├── providers/                 Youtube / Facebook / Instagram / Imgur
├── components/oembed/         editor component (transHTML)
├── views/admin/               config.blade.php, providers.blade.php
├── tpl/js/                    _ckeditor.js
├── tpl/css/                   card.css
└── lang/                      ko.php, en.php
```

## 컨트리뷰션

이슈와 PR 을 환영합니다. PR 시:

1. PHP 8.4 기준으로 작성하세요.
2. 새 provider 는 한 파일에 한 클래스, 파일명 = 클래스명 규칙을 지키세요.
3. 외부 호출이 있는 코드는 `RemoteFetcher` 를 통해서만 하세요(SSRF 가드 통과).
4. 사용자 입력은 항상 `htmlspecialchars(ENT_QUOTES, 'UTF-8')` 로 escape 하세요.

## 라이선스

MIT — [LICENSE](LICENSE) 참고.
