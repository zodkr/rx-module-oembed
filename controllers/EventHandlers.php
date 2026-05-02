<?php

namespace Rhymix\Modules\Oembed\Controllers;

use Rhymix\Modules\Oembed\Models\Config as ConfigModel;
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
  ];

  /**
   * before display 트리거.
   *
   * 글쓰기/댓글 페이지에서는 paste 훅(_ckeditor.js) 을, 글 보기 페이지에서는
   * 호환 모드 ON 일 때 레거시 임베드 후처리(_render.js) 를 주입한다.
   * card.css 는 두 그룹 모두 항상 로드한다.
   *
   * ─────────────────────────────────────────────────────────────────────
   *   임시 진단 마커: 진입/early-return 위치마다 페이지 source 에 HTML
   *   주석을 박는다. 메서드가 호출됐는지 여부와 어느 가드에서 멈췄는지
   *   브라우저에서 [페이지 소스 보기 → "oembed:" 검색] 으로 즉시 확인.
   *   진단 끝나면 "[TEMP-DIAG]" 표시된 줄 모두 제거.
   * ─────────────────────────────────────────────────────────────────────
   */
  public function injectEditorAssets(&$content)
  {
    // [TEMP-DIAG] 메서드 진입 자체 (response_method 가 HTML 이 아니면 마커가
    // 응답 본문에 안 보일 수 있음 — 그래도 error_log 한 번)
    @error_log('[oembed] injectEditorAssets fired, response_method=' . Context::getResponseMethod() . ', act=' . (Context::get('act') ?? '(null)'));

    if (Context::getResponseMethod() !== 'HTML') {
      return;
    }

    // [TEMP-DIAG] 여기까지 도달하면 HTML 응답 단계
    Context::addHtmlHeader('<!-- oembed:diag handler-entered response_method=HTML -->');

    $act = Context::get('act');
    $isWriteAct = in_array($act, self::WRITE_ACTS, true);
    $isViewAct = in_array($act, self::VIEW_ACTS, true);
    Context::addHtmlHeader(sprintf(
      '<!-- oembed:diag act=%s isWriteAct=%d isViewAct=%d -->',
      htmlspecialchars((string) $act, ENT_QUOTES, 'UTF-8'),
      $isWriteAct ? 1 : 0,
      $isViewAct ? 1 : 0
    ));
    if (!$isWriteAct && !$isViewAct) {
      Context::addHtmlHeader('<!-- oembed:diag skip act-not-in-allowed-list -->');
      return;
    }

    $modulePath = '/modules/oembed/';
    $skin = ConfigModel::getSkin();
    $skinCssPath = $modulePath . 'skins/' . $skin . '/card.css';

    if ($isWriteAct) {
      $mid = Context::get('mid');
      if (!$mid) {
        Context::addHtmlHeader('<!-- oembed:diag skip write-no-mid -->');
        return;
      }
      $moduleInfo = ModuleModel::getModuleInfoByMid($mid);
      $moduleSrl = isset($moduleInfo->module_srl) ? (int) $moduleInfo->module_srl : 0;
      if (!$moduleSrl) {
        Context::addHtmlHeader('<!-- oembed:diag skip write-no-module-srl -->');
        return;
      }
      $editorConfig = EditorModel::getEditorConfig($moduleSrl);
      $editorSkin = $act === 'dispBoardWrite'
        ? ($editorConfig->editor_skin ?? '')
        : ($editorConfig->comment_editor_skin ?? '');
      if ($editorSkin !== 'ckeditor') {
        Context::addHtmlHeader(sprintf(
          '<!-- oembed:diag skip write-not-ckeditor editor_skin=%s -->',
          htmlspecialchars((string) $editorSkin, ENT_QUOTES, 'UTF-8')
        ));
        return;
      }
      Context::addCssFile($modulePath . 'tpl/css/card.css');
      if (is_file(\RX_BASEDIR . ltrim($skinCssPath, '/'))) {
        Context::addCssFile($skinCssPath);
      }
      Context::addJsFile($modulePath . 'tpl/js/_ckeditor.js', '', '', 0, 'body');
      Context::addHtmlHeader('<script>window.current_mid=' . json_encode((string) $mid) . ';</script>');
      Context::addHtmlHeader(sprintf(
        '<!-- oembed:diag injected write-acts skin=%s -->',
        htmlspecialchars((string) $skin, ENT_QUOTES, 'UTF-8')
      ));
      return;
    }

    // VIEW_ACTS — 본문에 카드/임베드 마크업이 노출되는 페이지
    Context::addCssFile($modulePath . 'tpl/css/card.css');
    if (is_file(\RX_BASEDIR . ltrim($skinCssPath, '/'))) {
      Context::addCssFile($skinCssPath);
    }
    if (ConfigModel::isCompatibleMode()) {
      Context::addJsFile($modulePath . 'tpl/js/_render.js', '', '', 0, 'body');
    }
    Context::addHtmlHeader(sprintf(
      '<!-- oembed:diag injected view-acts skin=%s compat=%d -->',
      htmlspecialchars((string) $skin, ENT_QUOTES, 'UTF-8'),
      ConfigModel::isCompatibleMode() ? 1 : 0
    ));
  }
}
