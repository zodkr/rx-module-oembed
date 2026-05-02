<?php

namespace Rhymix\Modules\Oembed\Controllers;

use Rhymix\Framework\Filters\MediaFilter;
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
    $providers = Registry::getProviders();
    $hostStatus = [];
    foreach ($providers as $key => $provider) {
      foreach ($provider->hosts as $host) {
        $hostStatus[$key][$host] = MediaFilter::matchWhitelist('https://' . $host . '/');
      }
    }

    Context::set('oembed_config', ConfigModel::getConfig());
    Context::set('oembed_preview_active', ConfigModel::isPreviewModuleActive());
    Context::set('oembed_providers', $providers);
    Context::set('oembed_host_whitelist', $hostStatus);
    $this->setTemplateFile('providers');
  }

  public function procOembedAdminInsertConfig()
  {
    $config = ConfigModel::getConfig();
    $vars = Context::getRequestVars();
    $act = Context::get('act');

    if ($act === 'dispOembedAdminConfig') {
      $config->compatible_mode = (($vars->compatible_mode ?? 'N') === 'Y') ? 'Y' : 'N';
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

  /**
   * Provider 자동 스캔 캐시를 비우고 어드민 화면으로 돌려보낸다.
   * 새 provider 파일을 떨어뜨린 직후 변경 사항을 즉시 반영하기 위함.
   */
  public function procOembedAdminRefreshProviders()
  {
    Registry::flush();
    foreach (Registry::getProviders() as $provider) {
      foreach ($provider->hosts as $host) {
        MediaFilter::addPrefix($host, true);
      }
    }
    $this->setMessage('success_registed');
    $this->setRedirectUrl(Context::get('success_return_url'));
  }
}
