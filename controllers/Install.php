<?php

namespace Rhymix\Modules\Oembed\Controllers;

use ModuleModel;

class Install extends Base
{
  public function moduleInstall()
  {
    $this->checkPreviewConflict();
  }

  public function checkUpdate()
  {
    return false;
  }

  public function moduleUpdate()
  {
  }

  public function recompileCache()
  {
  }

  /**
   * preview 모듈이 활성화된 상태에서 oembed 가 설치되면 액션 라우팅이 충돌한다.
   * 충돌 시점에 분명한 경고를 띄워 사이트 운영자가 인지할 수 있도록 한다.
   */
  private function checkPreviewConflict(): void
  {
    $previewInfo = ModuleModel::getModuleInfoXml('preview');
    if (!$previewInfo) {
      return;
    }
    $logged = isset($GLOBALS['logged_info']) ? $GLOBALS['logged_info'] : null;
    if (is_object($logged) && (($logged->is_admin ?? '') === 'Y')) {
      \Context::set('oembed_preview_conflict', true);
    }
  }
}
