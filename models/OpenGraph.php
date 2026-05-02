<?php

namespace Rhymix\Modules\Oembed\Models;

/**
 * HTML → Open Graph / Twitter Card 메타데이터 추출.
 *
 * 우선순위는 og:* > twitter:* > <title> / <meta name="description"> 순.
 * 상대 URL 의 og:image 는 baseUrl 기준으로 절대 URL 로 정규화한다.
 */
class OpenGraph
{
  /**
   * @return array{title:string, description:string, image:string, site_name:string, type:string, url:string, host:string, locale:string}
   */
  public static function parse(string $html, string $baseUrl = ''): array
  {
    $result = [
      'title' => '',
      'description' => '',
      'image' => '',
      'site_name' => '',
      'type' => '',
      'url' => '',
      'host' => '',
      'locale' => '',
    ];

    if (trim($html) === '') {
      return $result;
    }

    $doc = new \DOMDocument();
    libxml_use_internal_errors(true);
    $doc->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NOERROR | LIBXML_NOWARNING);
    libxml_clear_errors();
    $xpath = new \DOMXPath($doc);

    foreach ($xpath->query('//meta[starts-with(@property, "og:") or starts-with(@name, "og:")]') ?: [] as $meta) {
      $prop = (string) ($meta->getAttribute('property') ?: $meta->getAttribute('name'));
      $content = trim((string) $meta->getAttribute('content'));
      if ($content === '') {
        continue;
      }
      switch ($prop) {
        case 'og:title':
          $result['title'] = $content;
          break;
        case 'og:description':
          $result['description'] = $content;
          break;
        case 'og:image':
        case 'og:image:url':
        case 'og:image:secure_url':
          if ($result['image'] === '') {
            $result['image'] = $content;
          }
          break;
        case 'og:site_name':
          $result['site_name'] = $content;
          break;
        case 'og:type':
          $result['type'] = $content;
          break;
        case 'og:url':
          $result['url'] = $content;
          break;
        case 'og:locale':
          $result['locale'] = $content;
          break;
      }
    }

    foreach ($xpath->query('//meta[starts-with(@name, "twitter:") or starts-with(@property, "twitter:")]') ?: [] as $meta) {
      $prop = (string) ($meta->getAttribute('name') ?: $meta->getAttribute('property'));
      $content = trim((string) $meta->getAttribute('content'));
      if ($content === '') {
        continue;
      }
      switch ($prop) {
        case 'twitter:title':
          $result['title'] = $result['title'] !== '' ? $result['title'] : $content;
          break;
        case 'twitter:description':
          $result['description'] = $result['description'] !== '' ? $result['description'] : $content;
          break;
        case 'twitter:image':
        case 'twitter:image:src':
          $result['image'] = $result['image'] !== '' ? $result['image'] : $content;
          break;
        case 'twitter:site':
          $result['site_name'] = $result['site_name'] !== '' ? $result['site_name'] : $content;
          break;
      }
    }

    if ($result['title'] === '') {
      $titleNode = $xpath->query('//title')->item(0);
      if ($titleNode) {
        $result['title'] = trim((string) $titleNode->textContent);
      }
    }
    if ($result['description'] === '') {
      $descNode = $xpath->query('//meta[@name="description"]')->item(0);
      if ($descNode) {
        $result['description'] = trim((string) $descNode->getAttribute('content'));
      }
    }

    if ($result['image'] !== '' && $baseUrl !== '' && !preg_match('#^https?://#i', $result['image'])) {
      $imageRel = $result['image'];
      $baseParts = parse_url($baseUrl);
      $baseScheme = $baseParts['scheme'] ?? 'https';
      $baseHost = $baseParts['host'] ?? '';
      if (str_starts_with($imageRel, '//')) {
        $result['image'] = $baseScheme . ':' . $imageRel;
      } elseif ($baseHost !== '') {
        $origin = $baseScheme . '://' . $baseHost;
        $result['image'] = str_starts_with($imageRel, '/')
          ? $origin . $imageRel
          : $origin . '/' . ltrim($imageRel, '/');
      }
    }

    $resolvedUrl = $result['url'] !== '' ? $result['url'] : $baseUrl;
    if ($resolvedUrl !== '') {
      $result['host'] = (string) (parse_url($resolvedUrl, PHP_URL_HOST) ?? '');
    }

    foreach (['title', 'description', 'site_name'] as $key) {
      if ($result[$key] !== '') {
        $result[$key] = html_entity_decode($result[$key], ENT_QUOTES | ENT_HTML5, 'UTF-8');
      }
    }

    return $result;
  }
}
