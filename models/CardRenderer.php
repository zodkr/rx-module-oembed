<?php

namespace Rhymix\Modules\Oembed\Models;

/**
 * Open Graph 데이터를 미리보기 카드 마크업으로 변환한다.
 *
 * 호환 모드 ON 시: preview 모듈이 사용하던 정확한 클래스명을 그대로 사용해,
 *   preview 시절 본문에 raw HTML 로 저장된 카드와 새로 만드는 카드가 동일한
 *   CSS 로 렌더되도록 한다 — preview 의 skins/default/preview_card.html 동등.
 * 호환 모드 OFF 시: oembed 고유의 oembed_card_* 클래스를 사용한다.
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

    $hrefHtml = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
    $titleHtml = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
    $descriptionHtml = htmlspecialchars($description, ENT_QUOTES, 'UTF-8');
    $imageTag = $image !== ''
      ? '<img src="' . htmlspecialchars($image, ENT_QUOTES, 'UTF-8') . '" alt="" />'
      : '';

    if (Config::isCompatibleMode()) {
      // preview 의 preview_card.html 과 동일한 host 라벨 형식.
      $hostLabel = $siteName !== '' ? 'from ' . mb_strtoupper($siteName) : $host;
      $hostHtml = htmlspecialchars($hostLabel, ENT_QUOTES, 'UTF-8');
      return sprintf(
        '<div class="preview_card_wrapper" contenteditable="false">'
        . '<a class="preview_card_link" href="%s" target="_blank" rel="noopener noreferrer">'
        . '%s'
        . '<span class="preview_card_text_container">'
        . '<span class="preview_card_title">%s</span>'
        . '<span class="preview_card_desc">%s</span>'
        . '<span class="preview_card_host">%s</span>'
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

    $hostHtml = htmlspecialchars($host, ENT_QUOTES, 'UTF-8');
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
