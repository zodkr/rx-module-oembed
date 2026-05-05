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
    if (ConfigModel::isCompatibleMode()) {
      Context::addJsFile($modulePath . 'tpl/js/_render.js', '', '', 0, 'body');
    }
  }
}
