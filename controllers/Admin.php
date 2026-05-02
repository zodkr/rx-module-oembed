<?php

namespace Rhymix\Modules\Oembed\Controllers;

use Rhymix\Framework\Filters\MediaFilter;
use Rhymix\Modules\Oembed\Models\Config as ConfigModel;
use Rhymix\Modules\Oembed\Models\Registry;
use Context;
use ModuleModel;

class Admin extends Base
{
  public function init()
  {
    $this->setTemplatePath($this->module_path . 'views/admin/');
  }

  public function dispOembedAdminConfig()
  {
    $skinList = ModuleModel::getSkins($this->module_path, 'skins');
    Context::set('oembed_config', ConfigModel::getConfig());
    Context::set('oembed_preview_active', ConfigModel::isPreviewModuleActive());
    Context::set('oembed_skin_list', is_array($skinList) ? $skinList : []);
    $this->setTemplateFile('config');
  }

  public function dispOembedAdminProviders()
  {
    // 어드민에서는 비활성화된 provider 도 보여야 다시 켤 수 있다.
    $providers = Registry::getProviders(false);
    $hostStatus = [];
    $missingHosts = [];
    foreach ($providers as $key => $provider) {
      foreach ($provider->hosts as $host) {
        $isWhitelisted = MediaFilter::matchWhitelist('https://' . $host . '/');
        $hostStatus[$key][$host] = $isWhitelisted;
        if (!$isWhitelisted) {
          $missingHosts[$host] = true;
        }
      }
    }

    Context::set('oembed_config', ConfigModel::getConfig());
    Context::set('oembed_preview_active', ConfigModel::isPreviewModuleActive());
    Context::set('oembed_providers', $providers);
    Context::set('oembed_host_whitelist', $hostStatus);
    Context::set('oembed_missing_hosts', array_keys($missingHosts));
    $this->setTemplateFile('providers');
  }

  public function procOembedAdminInsertConfig()
  {
    $config = ConfigModel::getConfig();
    $vars = Context::getRequestVars();
    $act = Context::get('act');

    if ($act === 'dispOembedAdminConfig') {
      $config->compatible_mode = (($vars->compatible_mode ?? 'N') === 'Y') ? 'Y' : 'N';
      $skin = trim((string) ($vars->skin ?? ''));
      if ($skin !== '' && is_dir($this->module_path . 'skins/' . $skin)) {
        $config->skin = $skin;
      }
    } elseif ($act === 'dispOembedAdminProviders') {
      // 폼은 enabled_providers[] (= 사용 체크) 만 전송한다.
      // 등록된 모든 provider 중 enabled 에 없는 것을 disabled 로 환산해 저장한다.
      $enabled = is_array($vars->enabled_providers ?? null)
        ? array_map('strval', $vars->enabled_providers)
        : [];
      $allKeys = array_keys(Registry::getProviders(false));
      $config->disabled_providers = array_values(array_diff($allKeys, $enabled));
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
   *
   * iframe 화이트리스트는 자동으로 등록하지 않는다. 운영자가 시스템 →
   * 설정 → 보안 → 외부 멀티미디어 허용 화면에서 직접 호스트를 승인해야
   * 임베드가 본문에 살아있게 된다.
   */
  public function procOembedAdminRefreshProviders()
  {
    Registry::flush();
    Registry::getProviders();
    $this->setMessage('success_registed');
    $this->setRedirectUrl(Context::get('success_return_url'));
  }
}
