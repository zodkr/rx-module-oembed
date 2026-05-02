<?php

namespace Rhymix\Modules\Oembed\Controllers;

use Rhymix\Modules\Oembed\Models\CardRenderer;
use Rhymix\Modules\Oembed\Models\ImageAttacher;
use Rhymix\Modules\Oembed\Models\OpenGraph;
use Rhymix\Modules\Oembed\Models\Provider;
use Rhymix\Modules\Oembed\Models\Registry;
use Rhymix\Modules\Oembed\Models\RemoteFetcher;
use Context;

class Controller extends Base
{
  private const URL_MAX_LENGTH = 2048;

  /**
   * 클라이언트(CKEditor) 가 paste/input 한 URL 을 받아 임베드 또는 미리보기 카드로
   * 변환한 결과를 JSON 으로 돌려준다.
   *
   * 응답:
   *   { kind: 'embed', wrapped_html, url, provider }
   *   { kind: 'card',  wrapped_html, url }
   *   { kind: 'fail' }
   *
   * 매칭 성공 → embed, 매칭 실패 → OG 카드 → 둘 다 실패하면 fail.
   * 카드 흐름은 v0.2.0 부터 활성화된다.
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
    if ($matched !== null) {
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
      return;
    }

    // OG 카드 흐름 (v0.2.0+)
    $fetched = RemoteFetcher::fetchHtml($url);
    if ($fetched === null) {
      $this->add('kind', 'fail');
      return;
    }
    $og = OpenGraph::parse($fetched['body'], $fetched['final_url'] !== '' ? $fetched['final_url'] : $url);
    if ($og['title'] === '' && $og['description'] === '' && $og['image'] === '') {
      $this->add('kind', 'fail');
      return;
    }

    $imageOverride = '';
    if ($og['image'] !== '') {
      $cached = ImageAttacher::attach($og['image']);
      if ($cached !== null) {
        $imageOverride = $cached;
      }
    }

    $cardHtml = CardRenderer::render($og, $url, $imageOverride);
    $wrappedHtml = sprintf(
      '<div editor_component="oembed" data-kind="card" data-url="%s">%s</div>',
      htmlspecialchars($url, ENT_QUOTES, 'UTF-8'),
      $cardHtml
    );

    $this->add('kind', 'card');
    $this->add('wrapped_html', $wrappedHtml);
    $this->add('url', $url);
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
