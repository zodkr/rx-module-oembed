<?php

namespace Rhymix\Modules\Oembed\Controllers;

use Rhymix\Modules\Oembed\Models\Config as ConfigModel;
use Rhymix\Modules\Oembed\Models\Registry;
use Context;

class Admin extends Base
{
  public function init()
  {
    $this->setTemplatePath($this->module_path . 'views/admin/');
  }

  public function dispOembedAdminConfig()
  {
    Context::set('oembed_config', ConfigModel::getConfig());
    Context::set('oembed_preview_active', ConfigModel::isPreviewModuleActive());
    $this->setTemplateFile('config');
  }

  public function dispOembedAdminProviders()
  {
    Context::set('oembed_config', ConfigModel::getConfig());
    Context::set('oembed_preview_active', ConfigModel::isPreviewModuleActive());
    Context::set('oembed_providers', Registry::getProviders());
    $this->setTemplateFile('providers');
  }

  public function procOembedAdminInsertConfig()
  {
    $config = ConfigModel::getConfig();
    $vars = Context::getRequestVars();
    $act = Context::get('act');

    if ($act === 'dispOembedAdminConfig') {
      $config->compatible_mode = $vars->compatible_mode === 'N' ? 'N' : 'Y';
    } elseif ($act === 'dispOembedAdminProviders') {
      $disabled = is_array($vars->disabled_providers ?? null) ? $vars->disabled_providers : [];
      $config->disabled_providers = array_values(array_filter(array_map('strval', $disabled)));
      Registry::flush();
    }

    $output = ConfigModel::setConfig($config);
    if (!$output->toBool()) {
      return $output;
    }

    $this->setMessage('success_registed');
    $this->setRedirectUrl(Context::get('success_return_url'));
  }
}
