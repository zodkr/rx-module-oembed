<?php

namespace Rhymix\Modules\Oembed\Controllers;

use Rhymix\Framework\Filters\MediaFilter;
use Rhymix\Modules\Oembed\Models\Provider;
use Rhymix\Modules\Oembed\Models\Registry;
use Context;

class Controller extends Base
{
  private const URL_MAX_LENGTH = 2048;

  /**
   * 클라이언트(CKEditor) 가 paste/input 한 URL 을 받아 임베드 또는 미리보기 카드로
   * 변환한 결과를 JSON 으로 돌려준다.
   *
   * 응답:
   *   { kind: 'embed', wrapped_html: '<div editor_component="oembed"...>', url, provider }
   *   { kind: 'fail' }
   *
   * v0.1.0 단계에서는 Registry 매칭에 성공한 경우에만 응답하고,
   * 실패하는 경우는 모두 fail 로 통일한다 (OG 카드는 v0.2.0).
   */
  public function procOembedFetch()
  {
    Context::setResponseMethod('JSON');

    $url = $this->normalizeUrl((string) Context::get('url'));
    if ($url === null) {
      $this->add('kind', 'fail');
      return;
    }

    $matched = Registry::match($url);
    if ($matched === null) {
      $this->add('kind', 'fail');
      return;
    }

    $provider = $matched['provider'];
    $matchData = $matched['match'];
    $width = (int) Context::get('width') ?: null;
    $height = (int) Context::get('height') ?: null;
    [$resolvedWidth, $resolvedHeight] = $provider->getDimensions($width, $height);

    $embedHtml = $provider->buildEmbed($matchData, $resolvedWidth, $resolvedHeight);
    if ($embedHtml === '') {
      $this->add('kind', 'fail');
      return;
    }

    foreach ($provider->hosts as $host) {
      MediaFilter::addPrefix($host, true);
    }

    $providerKey = $this->shortName($provider);
    $wrappedHtml = sprintf(
      '<div editor_component="oembed" data-kind="embed" data-url="%s" data-provider="%s" data-width="%d" data-height="%d">%s</div>',
      htmlspecialchars($url, ENT_QUOTES, 'UTF-8'),
      htmlspecialchars($providerKey, ENT_QUOTES, 'UTF-8'),
      $resolvedWidth,
      $resolvedHeight,
      $embedHtml
    );

    $this->add('kind', 'embed');
    $this->add('wrapped_html', $wrappedHtml);
    $this->add('url', $url);
    $this->add('provider', $providerKey);
  }

  public function procOembedAttachImage()
  {
    // v0.2.0 — OG 카드 흐름에서 OG 이미지를 첨부 파일로 변환할 때 사용.
  }

  public function procOembedTempImageDelete()
  {
  }

  public function procOembedFileDownload()
  {
  }

  public function procPreviewImageFileInfo()
  {
    return $this->procOembedAttachImage();
  }

  public function procPreviewImageTempFileDelete()
  {
    return $this->procOembedTempImageDelete();
  }

  public function procPreviewFileDownload()
  {
    return $this->procOembedFileDownload();
  }

  /**
   * URL 정규화: 빈/너무 긴 값 거부, 프로토콜 없으면 https:// 부여, host 검증.
   * 부적합하면 null.
   */
  private function normalizeUrl(string $raw): ?string
  {
    $url = trim($raw);
    if ($url === '' || strlen($url) > self::URL_MAX_LENGTH) {
      return null;
    }
    if (!preg_match('#^https?://#i', $url)) {
      $url = 'https://' . ltrim($url, '/');
    }
    $parts = parse_url($url);
    if (!is_array($parts) || empty($parts['host'])) {
      return null;
    }
    if (!preg_match('/^[a-z0-9.-]+\.[a-z]{2,}$/i', $parts['host'])) {
      return null;
    }
    return $url;
  }

  private function shortName(Provider $provider): string
  {
    $class = get_class($provider);
    $pos = strrpos($class, '\\');
    return $pos === false ? $class : substr($class, $pos + 1);
  }
}
