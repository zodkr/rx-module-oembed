<?php

namespace Rhymix\Modules\Oembed\Controllers;

use Rhymix\Modules\Oembed\Models\Config as ConfigModel;

class Install extends Base
{
  public function moduleInstall()
  {
    if (ConfigModel::isPreviewModuleActive()) {
      \Context::set('oembed_preview_conflict', true);
    }
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
}
