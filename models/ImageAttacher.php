<?php

namespace Rhymix\Modules\Oembed\Models;

use FileHandler;

/**
 * OG 이미지를 안전하게 다운로드해서 모듈 캐시 디렉터리에 저장하고
 * 그 절대 URL 을 돌려준다. 외부 이미지 호스트에 카드의 의존을 끊어
 * 외부 도메인 차단/만료 상황에서도 카드 표시가 깨지지 않게 한다.
 *
 * v0.2.0 단계에서는 file 모듈의 file_srl 매핑까지는 하지 않고,
 * 캐시된 이미지 URL 만 카드에 박아 둔다. 글 저장 시점에 file_srl 로
 * 변환하는 처리는 v0.2.x 후속 패치에서 추가한다.
 */
class ImageAttacher
{
  private const CACHE_PATH = 'files/cache/oembed/images/';
  private const ALLOWED_MIMES = [
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'image/gif' => 'gif',
    'image/webp' => 'webp',
  ];

  /**
   * Returns absolute URL of the cached image, or null on any failure.
   */
  public static function attach(string $imageUrl): ?string
  {
    if ($imageUrl === '' || !preg_match('#^https?://#i', $imageUrl)) {
      return null;
    }
    if (!RemoteFetcher::isUrlSafe($imageUrl)) {
      return null;
    }

    $image = RemoteFetcher::fetchImage($imageUrl);
    if ($image === null) {
      return null;
    }
    $ext = self::ALLOWED_MIMES[strtolower(trim(explode(';', $image['content_type'])[0]))] ?? null;
    if ($ext === null) {
      return null;
    }

    $hash = hash('sha256', $imageUrl . '|' . strlen($image['body']));
    $filename = substr($hash, 0, 32) . '.' . $ext;
    $targetDir = \RX_BASEDIR . self::CACHE_PATH;
    if (!is_dir($targetDir)) {
      FileHandler::makeDir($targetDir);
    }
    $targetPath = $targetDir . $filename;

    if (!file_exists($targetPath)) {
      $bytesWritten = @file_put_contents($targetPath, $image['body']);
      if ($bytesWritten === false || $bytesWritten === 0) {
        return null;
      }
    }

    return \RX_BASEURL . self::CACHE_PATH . $filename;
  }
}
