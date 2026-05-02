<?php

namespace Rhymix\Modules\Oembed\Controllers;

class View extends Base
{
  public function init()
  {
    $this->setTemplatePath($this->module_path . 'views/');
  }

  public function dispOembedCard()
  {
    // 살을 채우는 작업: v0.2.0 OG 카드 흐름에서 구현 (작업 5 procOembedFetch 와 별개)
  }

  public function dispOembedCardByData()
  {
  }

  public function dispOembedIframe()
  {
  }

  public function dispPreviewCard()
  {
    return $this->dispOembedCard();
  }

  public function dispPreviewCardByData()
  {
    return $this->dispOembedCardByData();
  }

  public function dispPreviewIframe()
  {
    return $this->dispOembedIframe();
  }
}
