<?php

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
 * 출력 시 transHTML 이 호출되어 data-* 속성으로부터 최종 HTML 을 다시 렌더링한다.
 * 재렌더가 불가능한 경우(provider 비활성화 등) body 를 그대로 반환해 안전하게 폴백한다.
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
      // 카드 마크업은 본문에 .preview_card_wrapper 가 이미 포함된 상태로 저장됨.
      // 추가 wrapper 없이 그대로 반환한다.
      return $body;
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
