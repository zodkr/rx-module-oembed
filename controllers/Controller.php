<?php

namespace Rhymix\Modules\Oembed\Controllers;

use Rhymix\Modules\Oembed\Models\CardRenderer;
use Rhymix\Modules\Oembed\Models\ImageAttacher;
use Rhymix\Modules\Oembed\Models\OpenGraph;
use Rhymix\Modules\Oembed\Models\Provider;
use Rhymix\Modules\Oembed\Models\Registry;
use Rhymix\Modules\Oembed\Models\RemoteFetcher;
use Context;

class Controller extends Base
{
  /**
   * 클라이언트(CKEditor) 가 paste/input 한 URL 을 받아 임베드 또는 미리보기 카드로
   * 변환한 결과를 JSON 으로 돌려준다.
   *
   * 응답:
   *   { kind: 'embed', wrapped_html, url, provider }
   *   { kind: 'card',  wrapped_html, url }
   *   { kind: 'fail' }
   *
   * 매칭 성공 → embed, 매칭 실패 → OG 카드 → 둘 다 실패하면 fail.
   * 카드 흐름은 v0.2.0 부터 활성화된다.
   */
  public function procOembedFetch()
  {
    Context::setResponseMethod('JSON');

    $url = RemoteFetcher::normalizeUrl((string) Context::get('url'));
    if ($url === null) {
      $this->add('kind', 'fail');
      return;
    }

    $matched = Registry::match($url);
    if ($matched !== null) {
      $provider = $matched['provider'];
      $matchData = $matched['match'];
      $width = (int) Context::get('width') ?: null;
      $height = (int) Context::get('height') ?: null;
      // provider 가 외부 메타정보를 가져올 수 있으면 (YouTube oEmbed 등) iframe
      // 비율을 영상 본연 비율로 맞춘다. 클라이언트가 명시 dimension 을 보낸
      // 경우 그 값이 우선이고, 응답은 비어 있는 축만 채운다.
      $info = $provider->fetchInfo($url);
      if ($info !== null) {
        $width = $width ?? ($info['width'] ?? null);
        $height = $height ?? ($info['height'] ?? null);
      }
      [$resolvedWidth, $resolvedHeight] = $provider->getDimensions($width, $height);

      $embedHtml = $provider->buildEmbed($matchData, $resolvedWidth, $resolvedHeight);
      if ($embedHtml === '') {
        $this->add('kind', 'fail');
        return;
      }

      $providerShort = $this->shortName($provider);
      $wrappedHtml = sprintf(
        '<div editor_component="oembed" data-oembed-type="%s" data-oembed-provider="%s" data-url="%s" contenteditable="false">%s</div>',
        htmlspecialchars($provider->type, ENT_QUOTES, 'UTF-8'),
        htmlspecialchars($providerShort, ENT_QUOTES, 'UTF-8'),
        htmlspecialchars($url, ENT_QUOTES, 'UTF-8'),
        $embedHtml
      );

      $this->add('kind', 'embed');
      $this->add('wrapped_html', $wrappedHtml);
      $this->add('url', $url);
      $this->add('provider', $providerShort);
      return;
    }

    // OG 카드 흐름 (v0.2.0+)
    $fetched = RemoteFetcher::fetchHtml($url);
    if ($fetched === null) {
      $this->add('kind', 'fail');
      return;
    }
    $og = OpenGraph::parse($fetched['body'], $fetched['final_url'] !== '' ? $fetched['final_url'] : $url);
    if ($og['title'] === '' && $og['description'] === '' && $og['image'] === '') {
      $this->add('kind', 'fail');
      return;
    }

    $imageOverride = '';
    $attachedFileSrl = 0;
    $editorSequence = (int) Context::get('editor_sequence');
    if ($og['image'] !== '') {
      // editor_sequence 가 함께 오면 file 모듈을 통해 본문 첨부로 등록한다.
      // 비어 있거나 첨부 등록이 실패하면 attach() 가 null 을 돌려주고,
      // CardRenderer 가 og.image 원본 URL 로 폴백한다.
      $attached = ImageAttacher::attach($og['image'], $editorSequence);
      if ($attached !== null) {
        $imageOverride = $attached['url'];
        $attachedFileSrl = $attached['file_srl'];
      }
    }

    $cardHtml = CardRenderer::render($og, $url, $imageOverride);
    // 카드에서 사용자가 지운 OG 이미지 첨부를 글 저장 시점에 정리할 수 있도록
    // file_srl 을 wrapper 에 박아 둔다 — EventHandlers::pruneOrphanedOembedFiles
    // 가 본문에서 이 속성을 수집해 미참조 파일을 가려낸다.
    $fileSrlAttr = $attachedFileSrl > 0
      ? sprintf(' data-oembed-file-srl="%d"', $attachedFileSrl)
      : '';
    $wrappedHtml = sprintf(
      '<div editor_component="oembed" data-oembed-type="card" data-url="%s"%s contenteditable="false">%s</div>',
      htmlspecialchars($url, ENT_QUOTES, 'UTF-8'),
      $fileSrlAttr,
      $cardHtml
    );

    $this->add('kind', 'card');
    $this->add('wrapped_html', $wrappedHtml);
    $this->add('url', $url);
    // procFileUpload 와 동일한 패턴: 첨부가 등록돼 새 upload_target_srl 이 발급됐으면
    // 응답에 실어 보내 클라이언트가 폼의 primary key (document_srl) hidden 필드를
    // 갱신하게 한다. 이 동기화가 없으면 저장 시 document_srl 이 새로 발급되어
    // setFilesValid 와 어긋나고 oembed 첨부가 고아 파일로 남는다.
    if ($editorSequence && !empty($_SESSION['upload_info'][$editorSequence]->upload_target_srl)) {
      $this->add('upload_target_srl', (int) $_SESSION['upload_info'][$editorSequence]->upload_target_srl);
    }
    // 응답이 stale 한 사이 (네트워크 지연, Ctrl+Z) 사용자가 placeholder 를 제거했다면
    // 클라이언트가 이 file_srl 을 procFileDelete 로 회수해 고아 파일이 글 저장에
    // 따라붙는 것을 막는다. file_srl 자체는 동일 editor_sequence 의 세션
    // upload_target_srl 에 묶여 있으므로 다른 사용자의 첨부에 영향을 주지 않는다.
    if ($attachedFileSrl > 0) {
      $this->add('file_srl', $attachedFileSrl);
    }
  }

  private function shortName(Provider $provider): string
  {
    $class = get_class($provider);
    $pos = strrpos($class, '\\');
    return $pos === false ? $class : substr($class, $pos + 1);
  }
}
