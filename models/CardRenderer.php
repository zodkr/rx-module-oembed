<?php

namespace Rhymix\Modules\Oembed\Models;

/**
 * Open Graph 데이터를 oembed 자체 미리보기 카드 마크업으로 변환한다.
 *
 * oembed 가 새로 만드는 카드는 항상 oembed_card_* 클래스로 출력한다.
 * preview 모듈의 preview_card_* 클래스는 *레거시 본문 호환* 을 위해
 * card.css 에 따로 정의되어 있을 뿐, 신규 카드 마크업과는 무관하다.
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
    $title = $og['title'] !== '' ? $og['title'] : ($og['host'] ?: $url);
    $description = $og['description'];
    $host = $og['host'] !== '' ? $og['host'] : (string) (parse_url($url, PHP_URL_HOST) ?? '');
    $siteName = $og['site_name'] ?? '';
    $image = $imageOverride !== '' ? $imageOverride : $og['image'];

    $hostLabel = $siteName !== '' ? $siteName : $host;

    $hrefHtml = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
    $titleHtml = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
    $descriptionHtml = htmlspecialchars($description, ENT_QUOTES, 'UTF-8');
    $hostHtml = htmlspecialchars($hostLabel, ENT_QUOTES, 'UTF-8');
    $imageTag = $image !== ''
      ? '<img src="' . htmlspecialchars($image, ENT_QUOTES, 'UTF-8') . '" alt="" />'
      : '';

    return sprintf(
      '<div class="oembed_card_wrapper" contenteditable="false">'
      . '<a class="oembed_card_link" href="%s" target="_blank" rel="noopener noreferrer">'
      . '%s'
      . '<span class="oembed_card_text">'
      . '<span class="oembed_card_title">%s</span>'
      . '<span class="oembed_card_description">%s</span>'
      . '<span class="oembed_card_host">%s</span>'
      . '</span>'
      . '</a>'
      . '</div>',
      $hrefHtml,
      $imageTag,
      $titleHtml,
      $descriptionHtml,
      $hostHtml
    );
  }
}
