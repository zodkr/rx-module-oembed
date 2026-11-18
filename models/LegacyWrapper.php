<?php

namespace Rhymix\Modules\Oembed\Models;

/**
 * v1.0.4 이하가 본문에 남긴 `<div editor_component="oembed" …>` wrapper 를
 * 현재 wrapper 형태 (`<div data-oembed-type="…" …>`) 로 정규화한다.
 *
 * 배경 — Rhymix 코어의 RVE-2026-31 패치 이후 두 가지가 바뀌었다.
 *
 *   1. 저장 단계 (document/comment controller → HTMLFilter::clean) 가 활성
 *      에디터 컴포넌트 목록을 allowlist 로 넘기므로, 등록된 컴포넌트가 아닌
 *      `editor_component="oembed"` 속성은 저장 시점에 제거된다.
 *   2. 출력 단계 (EditorController::transEditorComponent) 가 컴포넌트 객체를
 *      찾지 못한 태그를 `'<div>' . strip_tags(…) . '</div>'` 로 치환한다.
 *      → 패치 이전에 저장돼 속성이 DB 에 남아 있는 본문은 iframe / 카드가
 *      통째로 사라진다.
 *
 * 이 모듈은 더 이상 그 속성을 내보내지 않지만 (Controller 참고), 이미 저장된
 * 본문은 DB 에 그대로 있으므로 코어가 태그를 지우기 전에 속성을 떼어 내야
 * 한다. 호출 지점은 EventHandlers::normalizeLegacyWrappers /
 * normalizeLegacyCommentList 참고.
 *
 * 보안 — 코어 패치의 취지를 되돌리지 않기 위해 속성 하나만 지우는 대신
 * 여는 태그를 allowlist 로 재조립한다. 구 HTMLFilter 는 editor_component 가
 * 붙은 태그의 "알 수 없는 속성" 을 HTMLPurifier 검증 없이 통과시켰으므로
 * (`_encodeEditorComponents`), 그 경로로 들어왔을 수 있는 속성은 여기서
 * 버린다. 남기는 속성은 일반 div 에서도 HTMLPurifier / data-* 필터 검증을
 * 거쳐 살아남는 것들뿐이라, 정규화 결과는 "처음부터 editor_component 없이
 * 저장된 div" 와 동일한 신뢰 수준이 된다.
 */
class LegacyWrapper
{
  private const MARKER = 'editor_component';

  /** 여는 태그 재조립 시 유지할 속성. 그 외는 모두 버린다. */
  private const SAFE_ATTRIBUTE_REGEX = '~^(?:data-[a-z0-9_-]+|class|contenteditable|dir|id|lang|style|title)$~';

  /**
   * 본문 HTML 안의 legacy wrapper 여는 태그를 정규화해 돌려준다.
   * legacy wrapper 가 없으면 입력을 그대로 반환한다.
   */
  public static function normalize(string $content): string
  {
    if ($content === '' || stripos($content, self::MARKER) === false) {
      return $content;
    }

    // wrapper 는 항상 div 다. `<img editor_component="oembed">` 는 이 모듈이
    // 만든 적이 없으므로 건드리지 않고 코어 처리에 맡긴다.
    //
    // 여는 태그의 끝은 따옴표를 인식해서 찾는다. `[^>]*` 로 자르면 속성값
    // 안의 `>` 를 태그 끝으로 오인해, 값의 나머지 (브라우저에겐 그냥 속성
    // 텍스트) 가 재조립된 태그 뒤에 살아 있는 마크업으로 풀려난다. 필터를
    // 거친 본문은 값 안의 `>` 가 `&gt;` 로 이스케이프돼 있지만, 필터를 거치지
    // 않은 본문 (관리자 작성·이관 데이터) 까지 같은 가정을 하지는 않는다.
    // 소유(possessive) 수량자라 닫히지 않은 따옴표를 만나도 역추적하지 않고
    // 매칭 실패로 끝난다 — 그 태그는 손대지 않고 코어 처리에 맡긴다.
    $result = preg_replace_callback(
      '~<div(?=[\s/>])((?:[^>"\']++|"[^"]*+"|\'[^\']*+\')*+)>~i',
      static function (array $match): string {
        if (stripos($match[1], self::MARKER) === false) {
          return $match[0];
        }
        $attrs = self::parseAttributes($match[1]);
        if (($attrs[self::MARKER] ?? null) !== 'oembed') {
          return $match[0];
        }
        $kept = [];
        foreach ($attrs as $name => $value) {
          if (preg_match(self::SAFE_ATTRIBUTE_REGEX, $name)) {
            $kept[] = $name . '="' . self::encodeValue($value) . '"';
          }
        }
        return '<div' . ($kept === [] ? '' : ' ' . implode(' ', $kept)) . '>';
      },
      $content
    );

    return $result ?? $content;
  }

  /**
   * DocumentItem / CommentItem 의 content 를 제자리에서 정규화한다.
   * 메모리 상의 객체만 바꾸며 DB 나 캐시에는 쓰지 않는다.
   */
  public static function normalizeItem(object $item): void
  {
    if (!($item instanceof \DocumentItem) && !($item instanceof \CommentItem)) {
      return;
    }
    $content = $item->get('content');
    if (!is_string($content)) {
      return;
    }
    $normalized = self::normalize($content);
    if ($normalized === $content) {
      return;
    }
    $item->add('content', $normalized);
    // CommentItem::setAttribute 는 스킨 하위 호환용으로 각 컬럼을 객체
    // 프로퍼티에도 복제해 둔다 ($comment->content 직접 접근).
    if ($item instanceof \CommentItem && property_exists($item, 'content')) {
      $item->content = $normalized;
    }
  }

  /**
   * 유지하는 속성값을 디코드 후 다시 인코드해 `< > " ' &` 가 전부 엔티티로
   * 나가게 한다. 이미 인코드된 값은 왕복해도 그대로이고 (`&amp;` → `&` →
   * `&amp;`), 날것으로 들어 있던 `>` / `<` 는 여기서 무해화된다 — 코어의
   * 후속 정규식 (`[^>]*` 기반) 이 재조립된 태그를 잘못 자르지 않게 하는
   * 목적도 있다.
   */
  private static function encodeValue(string $value): string
  {
    return htmlspecialchars(
      html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8'),
      ENT_QUOTES | ENT_SUBSTITUTE,
      'UTF-8'
    );
  }

  /**
   * 여는 태그의 속성 문자열을 name => value 로 파싱한다. 이름은 소문자로
   * 맞추고, 중복 속성은 HTML 규칙대로 첫 번째만 채택한다.
   *
   * @return array<string, string>
   */
  private static function parseAttributes(string $raw): array
  {
    $attrs = [];
    if (!preg_match_all('~([^\s"\'<>/=]+)\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s"\'=<>`]+))~', $raw, $matches, PREG_SET_ORDER | PREG_UNMATCHED_AS_NULL)) {
      return $attrs;
    }
    foreach ($matches as $m) {
      $name = strtolower($m[1]);
      if (!isset($attrs[$name])) {
        $attrs[$name] = $m[2] ?? $m[3] ?? $m[4] ?? '';
      }
    }
    return $attrs;
  }
}
