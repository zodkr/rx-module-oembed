<?php

namespace Rhymix\Modules\Oembed\Controllers;

use Rhymix\Modules\Oembed\Models\Config as ConfigModel;
use Rhymix\Modules\Oembed\Models\Registry;
use Context;
use EditorModel;
use ModuleModel;

class EventHandlers extends Base
{
  private const WRITE_ACTS = [
    'dispBoardWrite', 'dispBoardWriteComment', 'dispBoardReplyComment', 'dispBoardModifyComment',
  ];
  private const VIEW_ACTS = [
    'dispBoardContent', 'dispDocumentPrint', 'dispDocumentPreview', 'dispTrashAdminView',
    // 어드민 신고/검토 화면 — 본문/댓글이 그대로 노출되므로 카드 자산이 필요.
    'dispDocumentAdminDeclared', 'dispDocumentAdminDeclaredLogByDocumentSrl',
    'dispCommentAdminDeclared', 'dispCommentAdminDeclaredLogByCommentSrl',
  ];

  /**
   * before display 트리거.
   *
   * 글쓰기/댓글 페이지에서는 paste 훅(_ckeditor.js) 을 주입한다.
   * 글 보기 페이지에서는 (1) card.css, (2) `_render.js` + provider 자산 맵을
   * 주입한다. `_render.js` 는 DOMContentLoaded 시점에 본문 DOM 을 스캔해
   * 매칭된 외부 SDK 를 `document.head` 에 동적 삽입한다.
   *
   * 서버측 `before display` 단계에서 본문 markup 을 스캔하지 않는 이유:
   * `before display` 는 `before_display_content` addon 보다 먼저 발사되며
   * (DisplayHandler.class.php:69 vs :73-76), 동일 트리거에 등록된 다른
   * 모듈(editor 모듈의 transComponent 등) 의 실행 순서도 보장되지 않는다.
   * 본문이 최종 형태로 굳어지기 전 시점이라 marker 누락 가능성이 있어,
   * DOM 이 확정된 클라이언트 시점에 검사하는 편이 견고하다.
   */
  public function injectEditorAssets(&$content)
  {
    if (Context::getResponseMethod() !== 'HTML') {
      return;
    }

    // act 정규화 — Rhymix 는 URL 에 act 가 없을 때 ModuleHandler 가 module 의
    // default_index_act 로 자동 라우팅하지만 Context::get('act') 자체에는
    // 그 값을 채우지 않는다 (ModuleHandler.class.php:355-358). 그래서 mid 만
    // 가지고 들어온 dispBoardContent 등이 화이트리스트와 매칭되지 않는다.
    // current_module_info 는 트리거 호출 시점에 이미 세팅되어 있으므로,
    // act 가 비어 있으면 모듈의 default/admin index act 를 폴백으로 채운다.
    $act = Context::get('act');
    if (!$act) {
      $cmi = Context::get('current_module_info');
      if ($cmi && !empty($cmi->module)) {
        $xml = ModuleModel::getModuleActionXml($cmi->module);
        if ($xml) {
          $act = $xml->default_index_act ?? ($xml->admin_index_act ?? '');
        }
      }
    }
    $isWriteAct = in_array($act, self::WRITE_ACTS, true);
    $isViewAct = in_array($act, self::VIEW_ACTS, true);
    if (!$isWriteAct && !$isViewAct) {
      return;
    }

    $modulePath = '/modules/oembed/';
    $skin = ConfigModel::getSkin();
    $skinCssPath = $modulePath . 'skins/' . $skin . '/card.css';

    if ($isWriteAct) {
      $mid = Context::get('mid');
      if (!$mid) {
        return;
      }
      $moduleInfo = ModuleModel::getModuleInfoByMid($mid);
      $moduleSrl = isset($moduleInfo->module_srl) ? (int) $moduleInfo->module_srl : 0;
      if (!$moduleSrl) {
        return;
      }
      $editorConfig = EditorModel::getEditorConfig($moduleSrl);
      $editorSkin = $act === 'dispBoardWrite'
        ? ($editorConfig->editor_skin ?? '')
        : ($editorConfig->comment_editor_skin ?? '');
      if ($editorSkin !== 'ckeditor') {
        return;
      }
      Context::addCssFile($modulePath . 'tpl/css/card.css');
      if (is_file(\RX_BASEDIR . ltrim($skinCssPath, '/'))) {
        Context::addCssFile($skinCssPath);
      }
      // 에디터 한정 — wysiwyg 영역 안 iframe 클릭 차단/사용자 선택 방지.
      // 글 보기 페이지에는 주입되지 않으므로 재생/스크롤 등 정상 상호작용 유지.
      Context::addCssFile($modulePath . 'tpl/css/editor.css');
      Context::addJsFile($modulePath . 'tpl/js/_ckeditor.js', '', '', 0, 'body');
      Context::addHtmlHeader('<script>window.current_mid=' . json_encode((string) $mid) . ';</script>');
      return;
    }

    // VIEW_ACTS — 본문에 카드/임베드 마크업이 노출되는 페이지
    Context::addCssFile($modulePath . 'tpl/css/card.css');
    if (is_file(\RX_BASEDIR . ltrim($skinCssPath, '/'))) {
      Context::addCssFile($skinCssPath);
    }

    $assets = $this->collectProviderEmbedAssets();
    $compatibleMode = ConfigModel::isCompatibleMode();
    if ($assets === [] && !$compatibleMode) {
      return;
    }

    // _render.js 는 DOM 스캔 후 매칭된 SDK 를 head 에 주입한다.
    // 어떤 provider 의 selector 가 어떤 script URL 로 매핑되는지는 이
    // inline payload 로 전달하므로, _render.js 자체는 데이터를 모른다.
    Context::addHtmlHeader(sprintf(
      '<script>window.oembedEmbedAssets=%s;window.oembedCompatibleMode=%s;</script>',
      json_encode($assets, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP),
      $compatibleMode ? 'true' : 'false'
    ));
    Context::addJsFile($modulePath . 'tpl/js/_render.js', '', '', 0, 'body');
  }

  /**
   * 등록된 모든 provider(비활성화 포함) 에서 client-side SDK 자산을 모은다.
   * disabled_providers 는 paste 단계 게이트일 뿐, 이미 저장된 본문은 계속
   * 정상 렌더되어야 하므로 view 시점엔 전체 provider 를 검사한다.
   *
   * crossorigin 은 SDK CDN 이 CORS 헤더를 보낼 때만 true. CORS 미지원
   * SDK (Instagram embed.js / X widgets.js 등) 에 anonymous 모드를 강제하면
   * 브라우저가 스크립트 로딩을 차단한다.
   *
   * normalize 는 sanitizer 가 떨어뜨린 클래스를 view 시점에 복원하기 위한
   * detect→addClass 규칙. SDK 검사보다 먼저 적용된다.
   *
   * @return array<int, array{
   *   selector: string,
   *   script: string,
   *   crossorigin: bool,
   *   normalize: array<int, array{detect: string, addClass: string}>,
   * }>
   */
  private function collectProviderEmbedAssets(): array
  {
    $assets = [];
    foreach (Registry::getProviders(false) as $provider) {
      foreach ($provider->getEmbedAssets() as $asset) {
        $selector = isset($asset['selector']) ? (string) $asset['selector'] : '';
        $script = isset($asset['script']) ? (string) $asset['script'] : '';
        if ($selector === '' || $script === '') {
          continue;
        }
        $normalize = [];
        if (isset($asset['normalize']) && is_array($asset['normalize'])) {
          foreach ($asset['normalize'] as $rule) {
            $detect = isset($rule['detect']) ? (string) $rule['detect'] : '';
            $addClass = isset($rule['addClass']) ? (string) $rule['addClass'] : '';
            if ($detect !== '' && $addClass !== '') {
              $normalize[] = ['detect' => $detect, 'addClass' => $addClass];
            }
          }
        }
        $assets[] = [
          'selector' => $selector,
          'script' => $script,
          'crossorigin' => !empty($asset['crossorigin']),
          'normalize' => $normalize,
        ];
      }
    }
    return $assets;
  }
}
