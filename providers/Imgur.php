<?php

namespace Rhymix\Modules\Oembed\Providers;

use Rhymix\Modules\Oembed\Models\Provider;

/**
 * Imgur 단일 이미지 / GIFV / 앨범 / 갤러리 임베드.
 *
 * 패턴 우선순위는 album → gallery → gifv → image(확장자 명시) → single
 * 이며 첫 매칭이 사용된다. single 패턴이 너무 관대해서 다른 패턴을
 * 가로채지 않도록 패턴 등록 순서를 통해 명시한다.
 *
 * 앨범/갤러리 blockquote 가 동작하려면 imgur embed.js 가 필요하지만 본문에
 * 함께 저장하면 HTMLPurifier 가 제거하므로 buildEmbed() 에서는 blockquote
 * 만 출력하고, 글 보기 시점에 EventHandlers 가 getEmbedAssets() 의 marker
 * 를 검사해 head 로 주입한다.
 */
class Imgur extends Provider
{
  public string $name = 'Imgur';
  public string $type = self::TYPE_MULTIMEDIA;
  public bool $oembed = false;
  public array $hosts = ['imgur.com', 'www.imgur.com', 'm.imgur.com', 'i.imgur.com', 's.imgur.com'];
  public array $patterns = [
    '#(?:https?:)?//(?:www\.|m\.)?imgur\.com/a/([\w]+)#i' => ['album_id'],
    '#(?:https?:)?//(?:www\.|m\.)?imgur\.com/gallery/([\w]+)#i' => ['gallery_id'],
    '#(?:https?:)?//i\.imgur\.com/([\w]+)\.gifv#i' => ['gifv_id'],
    '#(?:https?:)?//i\.imgur\.com/([\w]+)\.(jpg|jpeg|png|gif|webp)#i' => ['image_id', 'image_ext'],
    // 본문에 [?#] 가 있어 # delimiter 와 충돌하므로 ~ delimiter 사용.
    '~(?:https?:)?//(?:www\.|m\.)?imgur\.com/([\w]+)(?:[?#].*)?$~i' => ['single_id'],
  ];

  public function buildEmbed(array $matchData, ?int $width = null, ?int $height = null): string
  {
    $captures = $matchData['captures'] ?? [];

    if (isset($captures['album_id'])) {
      $id = $captures['album_id'];
      $idHtml = htmlspecialchars($id, ENT_QUOTES, 'UTF-8');
      return sprintf(
        '<blockquote class="imgur-embed-pub" lang="en" data-id="a/%s"><a href="//imgur.com/a/%s">View album on Imgur</a></blockquote>',
        $idHtml,
        $idHtml
      );
    }

    if (isset($captures['gallery_id'])) {
      $id = $captures['gallery_id'];
      $idHtml = htmlspecialchars($id, ENT_QUOTES, 'UTF-8');
      return sprintf(
        '<blockquote class="imgur-embed-pub" lang="en" data-id="%s"><a href="//imgur.com/gallery/%s">View on Imgur</a></blockquote>',
        $idHtml,
        $idHtml
      );
    }

    if (isset($captures['gifv_id'])) {
      $id = $captures['gifv_id'];
      $src = '//i.imgur.com/' . rawurlencode($id) . '.mp4';
      return sprintf(
        '<video src="%s" autoplay loop muted playsinline preload="auto" style="max-width:100%%;"></video>',
        htmlspecialchars($src, ENT_QUOTES, 'UTF-8')
      );
    }

    $imageId = $captures['image_id'] ?? $captures['single_id'] ?? '';
    if ($imageId === '') {
      return '';
    }
    $ext = $captures['image_ext'] ?? 'jpg';
    $src = '//i.imgur.com/' . rawurlencode($imageId) . '.' . $ext;
    $href = '//imgur.com/' . rawurlencode($imageId);
    return sprintf(
      '<a href="%s" target="_blank" rel="noopener noreferrer"><img src="%s" alt="" loading="lazy" style="max-width:100%%;" /></a>',
      htmlspecialchars($href, ENT_QUOTES, 'UTF-8'),
      htmlspecialchars($src, ENT_QUOTES, 'UTF-8')
    );
  }

  public function getEmbedAssets(): array
  {
    return [
      ['selector' => '.imgur-embed-pub', 'script' => 'https://s.imgur.com/min/embed.js'],
    ];
  }
}
