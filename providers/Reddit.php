<?php

namespace Rhymix\Modules\Oembed\Providers;

use Rhymix\Modules\Oembed\Models\Provider;

/**
 * Reddit 게시물/댓글 임베드.
 *
 * 공식 임베드 방식은 .reddit-embed-bq blockquote + embed.reddit.com/widgets.js
 * 조합. widgets.js 가 페이지 로드 후 .reddit-embed-bq 요소를 찾아 게시물
 * iframe 으로 변환한다. old.reddit.com / new.reddit.com / np.reddit.com /
 * 단축 URL(redd.it) 모두 동일한 SDK 가 처리한다.
 *
 * widgets.js 본문 인라인은 HTMLPurifier 가 제거하므로 buildEmbed() 에서는
 * blockquote 만 출력하고, 글 보기 시점에 EventHandlers 가 getEmbedAssets()
 * 의 selector 를 검사해 SDK 를 head 로 주입한다. CKEditor ACF 가
 * .reddit-embed-bq 클래스를 떨굴 가능성에 대비해 normalize 규칙으로
 * data-oembed-reddit-permalink 가 살아 있는 노드에 클래스를 복원한다.
 */
class Reddit extends Provider
{
  public string $name = 'Reddit';
  public string $type = self::TYPE_SOCIAL;
  public bool $oembed = false;
  public array $hosts = [
    'www.reddit.com', 'reddit.com',
    'old.reddit.com', 'new.reddit.com', 'np.reddit.com',
    'redd.it',
  ];
  // 좁은 패턴(comment_id 까지) 을 먼저 둬 두번째 패턴이 comment_id 를
  // 누락하지 않도록 한다 (Provider::match 는 first-match-wins).
  public array $patterns = [
    '~(?:https?:)?//(?:www\.|old\.|new\.|np\.)?reddit\.com/r/([\w-]+)/comments/(\w+)/[\w-]+/(\w+)/?~i' => ['subreddit', 'post_id', 'comment_id'],
    '~(?:https?:)?//(?:www\.|old\.|new\.|np\.)?reddit\.com/r/([\w-]+)/comments/(\w+)(?:/[\w-]+)?/?~i' => ['subreddit', 'post_id'],
    '~(?:https?:)?//redd\.it/(\w+)~i' => ['post_id'],
  ];

  public function buildEmbed(array $matchData, ?int $width = null, ?int $height = null): string
  {
    $postId = $matchData['captures']['post_id'] ?? '';
    if ($postId === '') {
      return '';
    }
    $subreddit = $matchData['captures']['subreddit'] ?? '';
    $commentId = $matchData['captures']['comment_id'] ?? '';

    if ($subreddit !== '') {
      $permalink = 'https://www.reddit.com/r/' . rawurlencode($subreddit) . '/comments/' . rawurlencode($postId) . '/';
      if ($commentId !== '') {
        $permalink .= '_/' . rawurlencode($commentId) . '/';
      }
    } else {
      $permalink = 'https://www.reddit.com/comments/' . rawurlencode($postId) . '/';
    }
    $hrefHtml = htmlspecialchars($permalink, ENT_QUOTES, 'UTF-8');

    return sprintf(
      '<blockquote class="reddit-embed-bq" data-oembed-reddit-permalink="%s" data-embed-height="500" style="height:500px">'
      . '<a href="%s" target="_blank" rel="noopener noreferrer">View on Reddit</a>'
      . '</blockquote>',
      $hrefHtml,
      $hrefHtml
    );
  }

  public function getEmbedAssets(): array
  {
    return [
      [
        'selector' => '.reddit-embed-bq',
        'script' => 'https://embed.reddit.com/widgets.js',
        // embed.reddit.com 은 CORS 헤더를 보내지 않으므로 anonymous 모드를
        // 강제하면 안 된다 (브라우저가 스크립트 로딩 차단).
        'normalize' => [
          ['detect' => 'blockquote[data-oembed-reddit-permalink]:not(.reddit-embed-bq)', 'addClass' => 'reddit-embed-bq'],
        ],
      ],
    ];
  }

  public function getEmbedHosts(): array
  {
    // SDK 호스트(embed.reddit.com) 와 SDK 가 변환한 iframe 의 호스트
    // (www.redditmedia.com) 둘 다 화이트리스트에 등록돼야 본문에 살아남는다.
    return ['embed.reddit.com', 'www.redditmedia.com'];
  }
}
