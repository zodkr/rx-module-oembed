<?php

namespace Rhymix\Modules\Oembed\Controllers;

use Rhymix\Modules\Oembed\Models\Config as ConfigModel;
use Rhymix\Modules\Oembed\Models\Registry;
use ModuleModel;

class Install extends Base
{
  public function moduleInstall()
  {
    if (ConfigModel::isPreviewModuleActive()) {
      \Context::set('oembed_preview_conflict', true);
    }

    // 신규 설치는 번들 provider 만 화이트리스트에 시드한다. 이후
    // providers/ 에 추가된 파일은 운영자가 어드민에서 명시적으로
    // 켜야만 paste 매칭 대상이 된다 (보안 정책).
    //
    // ConfigModel::getConfig() 는 fallback 으로 BUNDLED_PROVIDERS 를
    // 채워 반환하므로, "처음 설치인지" 판별은 raw 모듈 설정으로 한다.
    $raw = ModuleModel::getModuleConfig('oembed');
    if (!is_object($raw) || !isset($raw->enabled_providers) || !is_array($raw->enabled_providers)) {
      $config = ConfigModel::getConfig();
      $config->enabled_providers = ConfigModel::BUNDLED_PROVIDERS;
      ConfigModel::setConfig($config);
    }
  }

  /**
   * disabled_providers (블랙리스트) 만 가진 구버전 사이트는
   * enabled_providers (화이트리스트) 로 변환이 필요하다.
   * Config::getConfig() 의 fallback 이 enabled_providers 를 미리
   * 채우므로, 마이그레이션 판별은 반드시 raw 모듈 설정으로 한다.
   */
  public function checkUpdate()
  {
    $raw = ModuleModel::getModuleConfig('oembed');
    if (is_object($raw) && isset($raw->disabled_providers) && !isset($raw->enabled_providers)) {
      return true;
    }
    return false;
  }

  /**
   * 기존 enabled 상태를 보존하면서 모델을 반전한다.
   * enabled_providers = (등록된 모든 provider 키) − disabled_providers.
   */
  public function moduleUpdate()
  {
    $raw = ModuleModel::getModuleConfig('oembed');
    if (is_object($raw) && isset($raw->disabled_providers) && !isset($raw->enabled_providers)) {
      $disabled = is_array($raw->disabled_providers) ? $raw->disabled_providers : [];
      $allKeys = array_keys(Registry::getProviders(false));
      $config = ConfigModel::getConfig();
      $config->enabled_providers = array_values(array_diff($allKeys, $disabled));
      unset($config->disabled_providers);
      ConfigModel::setConfig($config);
      Registry::flush();
    }
  }

  public function recompileCache()
  {
  }
}
