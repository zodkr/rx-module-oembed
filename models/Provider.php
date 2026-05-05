<?php

namespace Rhymix\Modules\Oembed\Models;

abstract class Provider
{
  public const TYPE_MULTIMEDIA = 'multimedia';
  public const TYPE_SOCIAL = 'social';

  public string $name = '';
  public string $type = self::TYPE_MULTIMEDIA;
  public bool $oembed = false;
  public array $hosts = [];

  /**
   * Pattern map.
   *
   * key   : PCRE pattern (e.g. '#youtube\.com/watch\?v=([\w-]+)#i')
   * value : list of capture-group names whose order matches the pattern.
   *
   * Example: ['#youtube\.com/watch\?v=([\w-]+)#i' => ['video_id']]
   */
  public array $patterns = [];

  /**
   * Build the final embed HTML for matched data.
   *
   * @param array{pattern: string, captures: array<string,string>, url: string} $matchData
   */
  abstract public function buildEmbed(array $matchData, ?int $width = null, ?int $height = null): string;

  /**
   * Try to match the given URL against this provider's pattern map.
   * Returns null on miss, otherwise a struct describing the match.
   *
   * @return array{pattern: string, captures: array<string,string>, url: string}|null
   */
  public function match(string $url): ?array
  {
    foreach ($this->patterns as $pattern => $captureNames) {
      if (preg_match($pattern, $url, $m)) {
        $captures = [];
        foreach ($captureNames as $i => $key) {
          $captures[$key] = $m[$i + 1] ?? '';
        }
        return ['pattern' => $pattern, 'captures' => $captures, 'url' => $url];
      }
    }
    return null;
  }

  /**
   * 본문에 이 provider 의 임베드 마크업이 박제되어 있을 때, 글 보기
   * 페이지에서 자동으로 로드해야 할 외부 SDK 목록.
   *
   * selector 는 클라이언트의 `document.querySelector` 에 직접 전달되는
   * CSS 선택자다. 본문 DOM 에 실제로 매칭되는 노드가 있을 때만 SDK 가
   * 주입되므로, 서버측 트리거-addon 실행 순서나 addon 의 본문 후처리에
   * 무관하게 정확하다. 결정은 DOMContentLoaded 이후 시점이라 본문 markup
   * 은 이미 최종 DOM 에 들어가 있다.
   *
   * crossorigin 은 선택. Facebook SDK 처럼 CDN 이 CORS 헤더를 보내고
   * 공식 스니펫이 `crossorigin="anonymous"` 를 요구하는 경우에만 true 로
   * 둔다. Instagram embed.js / Imgur embed.js 처럼 CORS 헤더가 없는 SDK
   * 에 anonymous 모드를 켜면 브라우저가 로딩을 차단한다 (기본 false).
   *
   * normalize 는 선택. CKEditor ACF 나 HTMLPurifier 가 저장 시점에 우리
   * 가 buildEmbed() 에서 출력한 클래스를 화이트리스트에서 제외해 떨어뜨릴
   * 수 있는데(Instagram blockquote 의 .instagram-media 가 대표적), SDK
   * 가 자기 클래스를 보고 변환하는 구조라 클래스가 빠지면 임베드가 사문화
   * 된다. 각 항목은 sanitizer 를 통과한 detect selector(예: 사용자 정의
   * data 속성처럼 일반적으로 살아남는 식별자) 가 매칭되는 노드에 addClass
   * 를 붙이도록 _render.js 에 지시한다. SDK 검사보다 먼저 적용된다.
   *
   * buildEmbed() 결과 안에 <script> 를 직접 넣으면 HTMLPurifier 가 저장
   * 단계에서 제거하므로(글 저장 자체가 실패), script 는 반드시 이 메서드를
   * 통해 view 시점에 head 로 주입돼야 한다.
   *
   * @return array<int, array{
   *   selector: string,
   *   script: string,
   *   crossorigin?: bool,
   *   normalize?: array<int, array{detect: string, addClass: string}>,
   * }>
   */
  public function getEmbedAssets(): array
  {
    return [];
  }

  /**
   * Resolve final iframe dimensions.
   * If both axes are missing, fall back to type-aware defaults (multimedia=16:9, otherwise 4:3).
   *
   * @return array{0:int,1:int} [width, height]
   */
  public function getDimensions(?int $width, ?int $height): array
  {
    if ($width && $height) {
      return [$width, $height];
    }
    [$rw, $rh] = $this->type === self::TYPE_MULTIMEDIA ? [16, 9] : [4, 3];
    if ($width && !$height) {
      return [$width, (int) round($width * $rh / $rw)];
    }
    if ($height && !$width) {
      return [(int) round($height * $rw / $rh), $height];
    }
    $defaultWidth = 640;
    return [$defaultWidth, (int) round($defaultWidth * $rh / $rw)];
  }
}
