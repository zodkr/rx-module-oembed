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

    $url = RemoteFetcher::normalizeUrl((string) Context::get('url'));
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

      $providerShort = $this->shortName($provider);
      $wrappedHtml = sprintf(
        '<div editor_component="oembed" data-oembed-type="%s" data-oembed-provider="%s" data-url="%s" contenteditable="false">%s</div>',
        htmlspecialchars($provider->type, ENT_QUOTES, 'UTF-8'),
        htmlspecialchars($providerShort, ENT_QUOTES, 'UTF-8'),
        htmlspecialchars($url, ENT_QUOTES, 'UTF-8'),
        $embedHtml
      );

      $this->add('kind', 'embed');
      $this->add('wrapped_html', $wrappedHtml);
      $this->add('url', $url);
      $this->add('provider', $providerShort);
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
      '<div editor_component="oembed" data-oembed-type="card" data-url="%s" contenteditable="false">%s</div>',
      htmlspecialchars($url, ENT_QUOTES, 'UTF-8'),
      $cardHtml
    );

    $this->add('kind', 'card');
    $this->add('wrapped_html', $wrappedHtml);
    $this->add('url', $url);
  }

  private function shortName(Provider $provider): string
  {
    $class = get_class($provider);
    $pos = strrpos($class, '\\');
    return $pos === false ? $class : substr($class, $pos + 1);
  }
}
