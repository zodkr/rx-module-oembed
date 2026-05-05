<?php

namespace Rhymix\Modules\Oembed\Providers;

use Rhymix\Modules\Oembed\Models\Provider;

/**
 * Instagram 게시물 / 릴스 / IGTV 임베드.
 *
 * Meta oEmbed 가 토큰을 요구하므로 사용하지 않고, 공식 instagram embed.js
 * 와 .instagram-media blockquote 마크업으로 임베드한다.
 *
 * embed.js 는 본문에 함께 저장하면 HTMLPurifier 가 제거하므로 buildEmbed()
 * 에서는 blockquote 만 출력하고, 글 보기 시점에 EventHandlers 가
 * getEmbedAssets() 의 marker 를 검사해 head 로 주입한다.
 */
class Instagram extends Provider
{
  public string $name = 'Instagram';
  public string $type = self::TYPE_SOCIAL;
  public bool $oembed = false;
  public array $hosts = ['www.instagram.com', 'instagram.com'];
  public array $patterns = [
    '#(?:https?:)?//(?:www\.)?instagram\.com/(?:p|reel|reels|tv)/([\w-]+)#i' => ['shortcode'],
  ];

  public function buildEmbed(array $matchData, ?int $width = null, ?int $height = null): string
  {
    $shortcode = $matchData['captures']['shortcode'] ?? '';
    if ($shortcode === '') {
      return '';
    }
    $permalink = 'https://www.instagram.com/p/' . rawurlencode($shortcode) . '/';
    $hrefHtml = htmlspecialchars($permalink, ENT_QUOTES, 'UTF-8');

    return sprintf(
      '<blockquote class="instagram-media" data-instgrm-captioned data-instgrm-permalink="%s" data-instgrm-version="14" style="background:#FFF; border:0; border-radius:3px; box-shadow:0 0 1px 0 rgba(0,0,0,0.5),0 1px 10px 0 rgba(0,0,0,0.15); margin: 1px; max-width:540px; min-width:326px; padding:0; width:99.375%%; width:-webkit-calc(100%% - 2px); width:calc(100%% - 2px);">'
      . '<a href="%s" target="_blank" rel="noopener noreferrer">View on Instagram</a>'
      . '</blockquote>',
      $hrefHtml,
      $hrefHtml
    );
  }

  public function getEmbedAssets(): array
  {
    // CKEditor ACF / HTMLPurifier 가 blockquote 의 class 를 떨어뜨리는
    // 환경이 흔해서, 살아남는 data-instgrm-permalink 를 anchor 삼아
    // .instagram-media 클래스를 복원한 뒤 SDK 를 검사한다. data 속성이
    // 있는데도 클래스가 빠진 노드는 거의 항상 sanitizer 가 잘라낸 것.
    return [
      [
        'selector' => '.instagram-media',
        'script' => 'https://www.instagram.com/embed.js',
        'normalize' => [
          ['detect' => 'blockquote[data-instgrm-permalink]:not(.instagram-media)', 'addClass' => 'instagram-media'],
        ],
      ],
    ];
  }

  public function getEmbedHosts(): array
  {
    // SDK 가 변환한 iframe 도 embed.js 도 모두 www.instagram.com 호스팅.
    return ['www.instagram.com'];
  }
}
