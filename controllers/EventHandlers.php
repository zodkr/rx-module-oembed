<?php

namespace Rhymix\Modules\Oembed\Controllers;

use Context;
use EditorModel;
use ModuleModel;

class EventHandlers extends Base
{
  /**
   * before display 트리거. CKEditor 가 사용되는 페이지에서만 oembed 의 paste 훅
   * JS/CSS 를 주입한다. preview 모듈의 triggerPreviewAction 과 동등한 역할이며,
   * preview 가 비활성화되어도 oembed 만으로 paste 변환이 동작하도록 보장한다.
   */
  public function injectEditorAssets(&$content)
  {
    if (Context::getResponseMethod() !== 'HTML') {
      return;
    }

    $allowedActs = [
      'dispBoardWrite', 'dispBoardWriteComment', 'dispBoardReplyComment', 'dispBoardModifyComment',
      'dispBoardContent',
    ];
    $act = Context::get('act');
    if (!in_array($act, $allowedActs, true)) {
      return;
    }

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
    $isWriteAct = in_array($act, ['dispBoardWrite', 'dispBoardWriteComment', 'dispBoardReplyComment', 'dispBoardModifyComment'], true);
    $editorSkin = $isWriteAct
      ? ($act === 'dispBoardWrite' ? ($editorConfig->editor_skin ?? '') : ($editorConfig->comment_editor_skin ?? ''))
      : ($editorConfig->comment_editor_skin ?? '');
    if ($editorSkin !== 'ckeditor') {
      return;
    }

    $modulePath = '/modules/oembed/';
    Context::addCssFile($modulePath . 'tpl/css/card.css');
    Context::addJsFile($modulePath . 'tpl/js/_ckeditor.js', '', '', 0, 'body');

    Context::addHtmlHeader('<script>window.current_mid=' . json_encode((string) $mid) . ';</script>');
  }
}
