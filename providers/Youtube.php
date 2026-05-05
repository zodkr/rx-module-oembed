<?php

namespace Rhymix\Modules\Oembed\Providers;

use Rhymix\Modules\Oembed\Models\Provider;
use Rhymix\Modules\Oembed\Models\RemoteFetcher;

class Youtube extends Provider
{
  public string $name = 'YouTube';
  public string $type = self::TYPE_MULTIMEDIA;
  public bool $oembed = false;
  public array $hosts = ['www.youtube.com', 'youtube.com', 'm.youtube.com', 'youtu.be'];
  // watch 패턴은 본문에 [^#] 가 들어가서 # delimiter 와 충돌하므로 ~ delimiter 사용.
  // 다른 패턴도 일관성 있게 ~ 로 통일했다 (delimiter 와 본문 문자 충돌 방지).
  public array $patterns = [
    '~(?:https?:)?//(?:www\.|m\.)?youtube\.com/watch\?(?:[^#]*&)?v=([\w-]{6,})~i' => ['video_id'],
    '~(?:https?:)?//youtu\.be/([\w-]{6,})~i' => ['video_id'],
    '~(?:https?:)?//(?:www\.|m\.)?youtube\.com/shorts/([\w-]{6,})~i' => ['video_id'],
    '~(?:https?:)?//(?:www\.|m\.)?youtube\.com/embed/([\w-]{6,})~i' => ['video_id'],
  ];

  public function buildEmbed(array $matchData, ?int $width = null, ?int $height = null): string
  {
    $videoId = $matchData['captures']['video_id'] ?? '';
    if ($videoId === '') {
      return '';
    }
    [$w, $h] = $this->getDimensions($width, $height);
    $src = 'https://www.youtube.com/embed/' . rawurlencode($videoId);
    // max-width:100% 로 좁은 컨테이너에서 자연스럽게 줄고, aspect-ratio + height:auto
    // 로 width 가 줄어도 비율을 유지하며 height 가 자동 계산된다 (iframe 의 height
    // attribute 는 큰 컨테이너에서의 fallback 으로만 작용). aspect-ratio CSS 는
    // 모던 브라우저(Chrome 88+, Firefox 89+, Safari 15+)만 지원하지만 미지원 환경
    // 에서도 max-width:100% 는 살아남아 가로 overflow 는 막는다.
    return sprintf(
      '<iframe src="%s" width="%d" height="%d" style="max-width:100%%;aspect-ratio:%d/%d;height:auto;" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen loading="lazy"></iframe>',
      htmlspecialchars($src, ENT_QUOTES, 'UTF-8'),
      $w,
      $h,
      $w,
      $h
    );
  }

  public function getEmbedHosts(): array
  {
    // buildEmbed 가 출력하는 iframe 의 src 호스트만 화이트리스트에 필요.
    return ['www.youtube.com'];
  }

  public function fetchInfo(string $url): ?array
  {
    // 공식 oEmbed endpoint — 키 발급 불필요. width/height 은 영상의 실제 비율을
    // 반영해서 응답하므로(Shorts 9:16, 21:9 시네마틱 등 포함), 이 값을 그대로
    // iframe width/height 으로 쓰면 비율이 자동으로 맞는다. YouTube oEmbed 는
    // maxwidth 와 maxheight 가 *둘 다* 지정될 때만 그 한도를 적용한다 — 한쪽만
    // 주면 무시하고 356x200 같은 작은 기본값으로 응답한다. 854x480 (16:9 480p
    // 표준 — YouTube 의 default 임베드 크기와 같음) 으로 잡아 일반적인 본문
    // 폭(720~1000) 안에 fit. 더 작은 컨테이너는 buildEmbed 의 inline aspect-ratio
    // / max-width 가 처리한다.
    $endpoint = 'https://www.youtube.com/oembed?url=' . rawurlencode($url) . '&maxwidth=854&maxheight=480&format=json';
    $payload = RemoteFetcher::fetchJson($endpoint);
    if ($payload === null) {
      return null;
    }
    $info = [];
    if (isset($payload['width']) && is_numeric($payload['width'])) {
      $info['width'] = (int) $payload['width'];
    }
    if (isset($payload['height']) && is_numeric($payload['height'])) {
      $info['height'] = (int) $payload['height'];
    }
    if (!empty($payload['thumbnail_url']) && is_string($payload['thumbnail_url'])) {
      $info['thumbnail_url'] = $payload['thumbnail_url'];
    }
    return $info ?: null;
  }
}
