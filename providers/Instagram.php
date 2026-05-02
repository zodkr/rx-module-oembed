<?php

namespace Rhymix\Modules\Oembed\Providers;

use Rhymix\Modules\Oembed\Models\Provider;

/**
 * Instagram 게시물 / 릴스 / IGTV 임베드.
 *
 * Meta oEmbed 가 토큰을 요구하므로 사용하지 않고, 공식 instagram embed.js
 * 와 .instagram-media blockquote 마크업으로 임베드한다.
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
      . '</blockquote>'
      . '<script async src="//www.instagram.com/embed.js"></script>',
      $hrefHtml,
      $hrefHtml
    );
  }
}
