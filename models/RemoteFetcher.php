<?php

namespace Rhymix\Modules\Oembed\Models;

use Rhymix\Framework\HTTP;

/**
 * 외부 URL 에서 HTML/이미지를 가져오는 래퍼.
 *
 * - Rhymix\Framework\HTTP 사용 (Guzzle 기반)
 * - SSRF 가드: 입력 URL 과 redirect 대상 모두 사설/예약 IP 차단,
 *   localhost/메타데이터 엔드포인트(169.254.169.254) 차단
 * - 리다이렉트 5회 제한, body 사이즈 상한, 타임아웃 3초
 *
 * 실패 시 모두 null 을 돌려준다 (호출자는 graceful 폴백).
 */
class RemoteFetcher
{
  public const TIMEOUT_SECONDS = 3;
  public const MAX_REDIRECTS = 5;
  public const MAX_HTML_BYTES = 2 * 1024 * 1024; // 2MB
  public const MAX_IMAGE_BYTES = 5 * 1024 * 1024; // 5MB
  public const USER_AGENT = 'Mozilla/5.0 (compatible; OembedBot/0.1; +https://github.com/zodkr/rx-module-oembed)';

  /**
   * Fetch HTML at $url.
   *
   * @return array{status:int, body:string, final_url:string, content_type:string}|null
   */
  public static function fetchHtml(string $url): ?array
  {
    if (!self::isUrlSafe($url)) {
      return null;
    }
    $response = HTTP::get($url, null, [
      'User-Agent' => self::USER_AGENT,
      'Accept' => 'text/html,application/xhtml+xml,*/*;q=0.7',
      'Accept-Language' => 'ko,en;q=0.7',
    ], [], [
      'timeout' => self::TIMEOUT_SECONDS,
      'connect_timeout' => self::TIMEOUT_SECONDS,
      'allow_redirects' => self::redirectOptions(),
    ]);

    $status = $response->getStatusCode();
    if ($status < 200 || $status >= 300) {
      return null;
    }
    $contentType = $response->getHeaderLine('Content-Type');
    if (!preg_match('#(?:text/html|application/xhtml)#i', $contentType)) {
      return null;
    }

    $body = self::readLimitedBody($response, self::MAX_HTML_BYTES);
    $redirectHistory = $response->getHeader('X-Guzzle-Redirect-History');
    $finalUrl = end($redirectHistory) ?: $url;

    return [
      'status' => $status,
      'body' => $body,
      'final_url' => is_string($finalUrl) ? $finalUrl : $url,
      'content_type' => $contentType,
    ];
  }

  /**
   * Fetch image bytes at $url. Returns null on any failure or if the response
   * exceeds MAX_IMAGE_BYTES / is not an image MIME.
   *
   * @return array{body:string, content_type:string}|null
   */
  public static function fetchImage(string $url): ?array
  {
    if (!self::isUrlSafe($url)) {
      return null;
    }
    $response = HTTP::get($url, null, [
      'User-Agent' => self::USER_AGENT,
      'Accept' => 'image/*',
    ], [], [
      'timeout' => self::TIMEOUT_SECONDS,
      'connect_timeout' => self::TIMEOUT_SECONDS,
      'allow_redirects' => self::redirectOptions(),
    ]);

    $status = $response->getStatusCode();
    if ($status < 200 || $status >= 300) {
      return null;
    }
    $contentType = $response->getHeaderLine('Content-Type');
    if (!preg_match('#^image/#i', $contentType)) {
      return null;
    }
    $body = self::readLimitedBody($response, self::MAX_IMAGE_BYTES, true);
    if ($body === null) {
      return null;
    }
    return ['body' => $body, 'content_type' => $contentType];
  }

  /**
   * Validate the input URL.
   *  - scheme http/https only
   *  - host present, non-empty
   *  - DNS resolves to a public IP, or host is itself a public IP literal
   */
  public static function isUrlSafe(string $url): bool
  {
    $parts = parse_url($url);
    if (!is_array($parts)) {
      return false;
    }
    $scheme = strtolower($parts['scheme'] ?? '');
    if (!in_array($scheme, ['http', 'https'], true)) {
      return false;
    }
    return self::isHostSafe($parts['host'] ?? '');
  }

  /**
   * Validate a hostname (or IP literal). Loopback, link-local, RFC1918,
   * and the cloud-metadata IP are blocked. DNS is resolved to verify
   * every advertised A/AAAA record points to a public IP.
   */
  public static function isHostSafe(string $host): bool
  {
    $host = strtolower(trim($host, '[]'));
    if ($host === '' || $host === 'localhost' || str_ends_with($host, '.localhost')) {
      return false;
    }
    if (filter_var($host, FILTER_VALIDATE_IP)) {
      return self::isPublicIp($host);
    }
    $records = @dns_get_record($host, DNS_A | DNS_AAAA) ?: [];
    if (!$records) {
      return false;
    }
    foreach ($records as $record) {
      $ip = $record['ip'] ?? ($record['ipv6'] ?? '');
      if ($ip === '' || !self::isPublicIp($ip)) {
        return false;
      }
    }
    return true;
  }

  private static function isPublicIp(string $ip): bool
  {
    if ($ip === '169.254.169.254' || $ip === 'fd00:ec2::254') {
      return false;
    }
    return (bool) filter_var(
      $ip,
      FILTER_VALIDATE_IP,
      FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
    );
  }

  /**
   * @return array<string,mixed>
   */
  private static function redirectOptions(): array
  {
    return [
      'max' => self::MAX_REDIRECTS,
      'strict' => true,
      'protocols' => ['http', 'https'],
      'track_redirects' => true,
      'on_redirect' => static function ($request, $response, $uri) {
        if (!self::isHostSafe($uri->getHost())) {
          throw new \RuntimeException('oembed: redirect blocked (unsafe host)');
        }
      },
    ];
  }

  /**
   * Read the response body in chunks up to $maxBytes. If $rejectOversize is true
   * and the source exceeds $maxBytes, return null instead of truncating.
   */
  private static function readLimitedBody($response, int $maxBytes, bool $rejectOversize = false): ?string
  {
    $stream = $response->getBody();
    $body = '';
    while (!$stream->eof()) {
      $chunk = $stream->read(8192);
      if ($chunk === '' || $chunk === false) {
        break;
      }
      $body .= $chunk;
      if (strlen($body) > $maxBytes) {
        if ($rejectOversize) {
          return null;
        }
        return substr($body, 0, $maxBytes);
      }
    }
    return $body;
  }
}
