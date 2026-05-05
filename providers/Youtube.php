<?php

namespace Rhymix\Modules\Oembed\Providers;

use Rhymix\Modules\Oembed\Models\Provider;

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
}
