<?php

namespace Rhymix\Modules\Oembed\Models;

/**
 * Open Graph 데이터를 활성 *스킨* 의 카드 템플릿으로 렌더링한다.
 *
 * 마크업과 스타일은 modules/oembed/skins/{skin}/card.{blade.php,html} +
 * card.css 가 책임지며, CardRenderer 자체는 OG 입력을 정규화해 템플릿에
 * 변수로 넘기는 역할만 한다. 활성 스킨이 실제 디렉터리에 없으면
 * Config::getSkin() 이 default 로 폴백한다.
 */
class CardRenderer
{
  /**
   * @param array{title:string, description:string, image:string, site_name:string, type:string, url:string, host:string, locale:string} $og
   * @param string $url     사용자가 붙여넣은 원본 URL (a href 값)
   * @param string $imageOverride  ImageAttacher 가 로컬 첨부로 변환한 이미지 URL.
   *                               빈 문자열이면 og.image 를 그대로 사용.
   */
  public static function render(array $og, string $url, string $imageOverride = ''): string
  {
    $host = $og['host'] !== '' ? $og['host'] : (string) (parse_url($url, PHP_URL_HOST) ?? '');
    $title = $og['title'] !== '' ? $og['title'] : ($host !== '' ? $host : $url);
    $source = $og['site_name'] !== '' ? $og['site_name'] : $host;
    $image = $imageOverride !== '' ? $imageOverride : $og['image'];

    $cardData = [
      'url' => $url,
      'title' => $title,
      'description' => $og['description'],
      'image' => $image,
      'source' => $source,
    ];

    $skin = Config::getSkin();
    $tplPath = \RX_BASEDIR . 'modules/oembed/skins/' . $skin . '/';

    \Context::set('oembed_card', $cardData);
    return (string) \TemplateHandler::getInstance()->compile($tplPath, 'card');
  }
}
