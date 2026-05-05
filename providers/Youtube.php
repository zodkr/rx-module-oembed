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
    return sprintf(
      '<iframe src="%s" width="%d" height="%d" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen loading="lazy"></iframe>',
      htmlspecialchars($src, ENT_QUOTES, 'UTF-8'),
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
    // iframe width/height 으로 쓰면 비율이 자동으로 맞는다.
    $endpoint = 'https://www.youtube.com/oembed?url=' . rawurlencode($url) . '&format=json';
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
