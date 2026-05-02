<?php

namespace Rhymix\Modules\Oembed\Controllers;

use Rhymix\Modules\Oembed\Models\CardRenderer;
use Rhymix\Modules\Oembed\Models\ImageAttacher;
use Rhymix\Modules\Oembed\Models\OpenGraph;
use Rhymix\Modules\Oembed\Models\RemoteFetcher;
use Context;

/**
 * RAW response 외부 액션 + preview 호환 별칭.
 *
 * dispOembedCard / dispOembedCardByData / dispOembedIframe 는 이메일·외부
 * 캐시처럼 *Rhymix 사이트 외부* 에서 직접 호출되는 액션이다. layout 없이
 * 카드 또는 iframe 마크업만 RAW 로 응답한다.
 *
 * preview 모듈의 dispPreviewCard 시리즈와 호출 시그니처(GET 의 url, data,
 * layout=none) 가 동일하므로, module.xml 의 별칭 액션이 이 메서드들에
 * 그대로 위임되어도 외부 캐시·이메일이 깨지지 않는다.
 */
class View extends Base
{
  public function init()
  {
    $this->setTemplatePath($this->module_path . 'views/');
  }

  public function dispOembedCard()
  {
    Context::setResponseMethod('RAW');
    $this->setTemplateFile('preview_card');

    $url = RemoteFetcher::normalizeUrl((string) Context::get('url'));
    if ($url === null) {
      return;
    }

    $fetched = RemoteFetcher::fetchHtml($url);
    if ($fetched === null) {
      return;
    }

    $og = OpenGraph::parse(
      $fetched['body'],
      $fetched['final_url'] !== '' ? $fetched['final_url'] : $url
    );
    if ($og['title'] === '' && $og['description'] === '' && $og['image'] === '') {
      return;
    }

    $imageOverride = '';
    if ($og['image'] !== '') {
      $cached = ImageAttacher::attach($og['image']);
      if ($cached !== null) {
        $imageOverride = $cached;
      }
    }

    Context::set('oembed_card_html', CardRenderer::render($og, $url, $imageOverride));
  }

  /**
   * 외부에서 미리 만든 OG 데이터(JSON) 를 받아 카드 마크업으로 렌더한다.
   * preview 의 dispPreviewCardByData 와 호출 시그니처가 동일하다.
   */
  public function dispOembedCardByData()
  {
    Context::setResponseMethod('RAW');
    $this->setTemplateFile('preview_card');

    $url = RemoteFetcher::normalizeUrl((string) Context::get('url'));
    $rawData = (string) Context::get('data');
    if ($url === null || $rawData === '') {
      return;
    }

    $decoded = json_decode(html_entity_decode($rawData), true);
    if (!is_array($decoded)) {
      return;
    }

    $og = [
      'title' => (string) ($decoded['title'] ?? ''),
      'description' => (string) ($decoded['description'] ?? ''),
      'image' => (string) ($decoded['image'] ?? ''),
      'site_name' => (string) ($decoded['site_name'] ?? ''),
      'type' => (string) ($decoded['type'] ?? ''),
      'url' => $url,
      'host' => (string) (parse_url($url, PHP_URL_HOST) ?? ''),
      'locale' => (string) ($decoded['locale'] ?? ''),
    ];

    Context::set('oembed_card_html', CardRenderer::render($og, $url));
  }

  /**
   * preview 의 dispPreviewIframe 은 사실상 사용되지 않는 placeholder 였다.
   * 호환을 위해 동등한 invalid_request 응답으로 둔다.
   */
  public function dispOembedIframe()
  {
    return new \BaseObject(-1, 'msg_invalid_request');
  }

  public function dispPreviewCard()
  {
    return $this->dispOembedCard();
  }

  public function dispPreviewCardByData()
  {
    return $this->dispOembedCardByData();
  }

  public function dispPreviewIframe()
  {
    return $this->dispOembedIframe();
  }
}
