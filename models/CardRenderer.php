<?php

namespace Rhymix\Modules\Oembed\Models;

/**
 * Open Graph 데이터를 미리보기 카드 마크업으로 변환한다.
 * 호환 모드(기본값) 에서는 preview 모듈의 클래스명(preview_card_*) 을 그대로
 * 사용해 기존 스킨 CSS 가 깨지지 않게 한다.
 */
class CardRenderer
{
  /**
   * @param array{title:string, description:string, image:string, site_name:string, type:string, url:string, host:string, locale:string} $og
   * @param string $url     사용자가 붙여넣은 원본 URL (a href 의 값)
   * @param string $imageOverride  ImageAttacher 가 로컬 첨부로 변환한 이미지 URL.
   *                               빈 문자열이면 og.image 를 그대로 사용.
   */
  public static function render(array $og, string $url, string $imageOverride = ''): string
  {
    $title = $og['title'] !== '' ? $og['title'] : ($og['host'] ?: $url);
    $description = $og['description'];
    $host = $og['host'] !== '' ? $og['host'] : (string) (parse_url($url, PHP_URL_HOST) ?? '');
    $image = $imageOverride !== '' ? $imageOverride : $og['image'];

    $hrefHtml = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
    $titleHtml = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
    $descriptionHtml = htmlspecialchars($description, ENT_QUOTES, 'UTF-8');
    $hostHtml = htmlspecialchars($host, ENT_QUOTES, 'UTF-8');
    $imageHtml = $image !== ''
      ? '<div class="preview_card_image" style="background-image:url(\'' . htmlspecialchars($image, ENT_QUOTES, 'UTF-8') . '\');"></div>'
      : '';

    return sprintf(
      '<div class="preview_card_wrapper" contenteditable="false">'
      . '<a href="%s" class="preview_card_link" target="_blank" rel="noopener noreferrer">'
      . '%s'
      . '<div class="preview_card_text">'
      . '<div class="preview_card_title">%s</div>'
      . '<div class="preview_card_description">%s</div>'
      . '<div class="preview_card_host">%s</div>'
      . '</div>'
      . '</a>'
      . '</div>',
      $hrefHtml,
      $imageHtml,
      $titleHtml,
      $descriptionHtml,
      $hostHtml
    );
  }
}
