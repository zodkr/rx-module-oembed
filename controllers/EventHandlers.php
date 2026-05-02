<?php

namespace Rhymix\Modules\Oembed\Controllers;

class EventHandlers extends Base
{
  /**
   * before display 트리거. CKEditor 가 로드된 페이지에서만 oembed 의 paste 훅
   * JS/CSS 를 주입한다. 본 구현은 작업 6 에서 채워진다.
   */
  public function injectEditorAssets()
  {
  }
}
