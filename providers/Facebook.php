<?php

namespace Rhymix\Modules\Oembed\Providers;

use Rhymix\Modules\Oembed\Models\Provider;

/**
 * Facebook 게시물/비디오/Watch 임베드.
 *
 * Meta(Graph) oEmbed 는 페이지 액세스 토큰이 필요하므로 사용하지 않는다.
 * 대신 공식 connect.facebook.net SDK + .fb-post / .fb-video XFBML 마크업
 * 으로 클라이언트에서 임베드가 자동 변환되도록 한다.
 *
 * SDK 스크립트는 본문에 함께 저장하면 HTMLPurifier 가 제거하므로 buildEmbed()
 * 에서는 div 만 출력하고, 글 보기 시점에 EventHandlers 가 getEmbedAssets()
 * 의 marker 를 검사해 head 로 주입한다.
 */
class Facebook extends Provider
{
  public string $name = 'Facebook';
  public string $type = self::TYPE_SOCIAL;
  public bool $oembed = false;
  public array $hosts = ['www.facebook.com', 'facebook.com', 'm.facebook.com', 'fb.watch', 'connect.facebook.net'];
  public array $patterns = [
    '#(?:https?:)?//(?:www\.|m\.)?facebook\.com/(?:[\w.\-]+)/posts/[\w-]+#i' => [],
    '#(?:https?:)?//(?:www\.|m\.)?facebook\.com/permalink\.php\?[^"\s]*story_fbid=[\w-]+#i' => [],
    '#(?:https?:)?//(?:www\.|m\.)?facebook\.com/(?:[\w.\-]+)/videos/[\w-]+#i' => ['video' => true],
    '#(?:https?:)?//(?:www\.|m\.)?facebook\.com/watch/?\?v=[\w-]+#i' => ['video' => true],
    '#(?:https?:)?//fb\.watch/[\w-]+/?#i' => ['video' => true],
    '#(?:https?:)?//(?:www\.|m\.)?facebook\.com/photo/?\?[^"\s]*fbid=[\w-]+#i' => [],
  ];

  public function buildEmbed(array $matchData, ?int $width = null, ?int $height = null): string
  {
    $url = $matchData['url'] ?? '';
    if ($url === '') {
      return '';
    }
    $isVideo = preg_match('#/videos/|/watch/?\?v=|fb\.watch/#i', $url) === 1;
    $cssClass = $isVideo ? 'fb-video' : 'fb-post';
    $hrefHtml = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
    [$resolvedWidth] = $this->getDimensions($width, $height);

    return sprintf(
      '<div class="%s" data-href="%s" data-width="%d"></div>',
      $cssClass,
      $hrefHtml,
      $resolvedWidth
    );
  }

  public function getEmbedAssets(): array
  {
    return [
      [
        'selector' => '.fb-post, .fb-video',
        'script' => 'https://connect.facebook.net/ko_KR/sdk.js#xfbml=1&version=v18.0',
        'crossorigin' => true,
      ],
    ];
  }
}
