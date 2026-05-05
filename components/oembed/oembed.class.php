<?php

use Rhymix\Modules\Oembed\Models\CardRenderer;
use Rhymix\Modules\Oembed\Models\Registry;

/**
 * oEmbed editor component.
 *
 * 본문에 저장된 마크업:
 *   <div editor_component="oembed" data-kind="embed|card" data-url="..."
 *        data-provider="Youtube" data-width="640" data-height="360">
 *     ...최초 삽입 시 생성된 HTML...
 *   </div>
 *
 * 카드는 paste 시점에 채워진 data-{title,desc,image,source} 만을 신뢰해
 * 출력 때마다 CardRenderer 로 재렌더링한다. body 의 내용(사용자가 HTML 모드에서
 * 임의 편집했을 수 있음)은 무시되어 위변조 가능성을 차단한다.
 *
 * 임베드는 data-provider 로 Provider 를 다시 매칭해 buildEmbed 결과를 출력한다.
 */
class oembed extends EditorHandler
{
  public int $editor_sequence = 0;
  public string $component_path = '';

  function __construct($editor_sequence, $component_path)
  {
    $this->editor_sequence = (int) $editor_sequence;
    $this->component_path = $component_path;
  }

  function transHTML($xml_obj)
  {
    $attrs = $xml_obj->attrs ?? new \stdClass();
    $kind = $attrs->{'data-kind'} ?? 'embed';
    $url = trim((string) ($attrs->{'data-url'} ?? ''));
    $providerKey = trim((string) ($attrs->{'data-provider'} ?? ''));
    $width = (int) ($attrs->{'data-width'} ?? 0) ?: null;
    $height = (int) ($attrs->{'data-height'} ?? 0) ?: null;
    $body = (string) ($xml_obj->body ?? '');

    if ($kind === 'card') {
      if ($url === '' || !preg_match('#^https?://#i', $url)) {
        return '';
      }
      $title = trim((string) ($attrs->{'data-title'} ?? ''));
      $description = trim((string) ($attrs->{'data-desc'} ?? ''));
      $image = trim((string) ($attrs->{'data-image'} ?? ''));
      $source = trim((string) ($attrs->{'data-source'} ?? ''));

      if ($title === '' && $description === '' && $image === '') {
        // 레거시(v0.2.0 이전) 마크업 폴백: data-url 만 신뢰해 단순 링크로 표시.
        $safe = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
        return '<div class="oembed_wrapper" contenteditable="false"><a href="' . $safe . '" target="_blank" rel="noopener noreferrer">' . $safe . '</a></div>';
      }

      $og = [
        'title' => $title,
        'description' => $description,
        'image' => $image,
        'site_name' => $source,
        'type' => '',
        'url' => $url,
        'host' => (string) (parse_url($url, PHP_URL_HOST) ?? ''),
        'locale' => '',
      ];
      return '<div class="oembed_wrapper" contenteditable="false">' . CardRenderer::render($og, $url) . '</div>';
    }

    if ($kind === 'embed' && $url !== '' && $providerKey !== '') {
      $providers = Registry::getProviders();
      $provider = $providers[$providerKey] ?? null;
      if ($provider !== null) {
        $matchData = $provider->match($url);
        if ($matchData !== null) {
          $rendered = $provider->buildEmbed($matchData, $width, $height);
          if ($rendered !== '') {
            return '<div class="oembed_wrapper" contenteditable="false">' . $rendered . '</div>';
          }
        }
      }
    }

    return '<div class="oembed_wrapper" contenteditable="false">' . $body . '</div>';
  }
}
