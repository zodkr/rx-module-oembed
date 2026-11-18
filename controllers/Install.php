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
      // 저장된 설정이 전혀 없는 신규 설치 — 이전 버전이 남긴 본문이 있을 수
      // 없으므로 legacy wrapper 정규화는 끈 채로 시작한다. 여기서 판정을
      // 끝내 두면 이후 moduleUpdate 가 다시 판정하지 않는다.
      if (!isset($config->legacy_wrappers)) {
        $config->legacy_wrappers = 'N';
      }
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
    // legacy wrapper 정규화를 켤지 아직 판정하지 않았다 (moduleUpdate 참고).
    if (!is_object($raw) || !isset($raw->legacy_wrappers)) {
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
    // ConfigModel::getConfig() 는 $raw 와 같은 객체에 기본값을 채워 넣으므로,
    // 두 판별 모두 그 전에 끝내 둔다.
    $migrateProviders = is_object($raw) && isset($raw->disabled_providers) && !isset($raw->enabled_providers);
    $decideLegacyWrappers = !is_object($raw) || !isset($raw->legacy_wrappers);

    if ($migrateProviders) {
      $disabled = is_array($raw->disabled_providers) ? $raw->disabled_providers : [];
      $allKeys = array_keys(Registry::getProviders(false));
      $config = ConfigModel::getConfig();
      $config->enabled_providers = array_values(array_diff($allKeys, $disabled));
      unset($config->disabled_providers);
      ConfigModel::setConfig($config);
      Registry::flush();
    }

    // legacy wrapper 정규화 (ConfigModel::shouldNormalizeLegacyWrappers) 는
    // 이전 버전에서 업데이트한 사이트에서만 켠다. 판정은 한 번만 하고 결과를
    // 저장한다 — 이후엔 운영자가 어드민에서 바꾼 값이 유지된다.
    if ($decideLegacyWrappers) {
      $config = ConfigModel::getConfig();
      $config->legacy_wrappers = $this->wasActiveBeforeThisUpdate() ? 'Y' : 'N';
      ConfigModel::setConfig($config);
    }
  }

  /**
   * 이 업데이트가 실행되기 전부터 모듈이 가동 중이었는지 — 즉 "신규 설치" 가
   * 아니라 "이전 버전에서의 업데이트" 인지 판별한다.
   *
   * 기준은 oembed 의 트리거가 이미 등록돼 있는지다. 코어는 모듈 업데이트
   * 때 moduleUpdate() 를 먼저 부르고 그 다음에 conf/module.xml 의
   * eventHandlers 를 등록하므로 (install.admin.controller 의
   * procInstallAdminUpdate, install.controller 의 updateModule 둘 다 같은
   * 순서), 이 시점에 트리거가 보이면 이전 업데이트에서 등록된 것이다.
   * 신규 설치는 최초 업데이트 전까지 트리거가 하나도 없다. 그리고 paste 훅은
   * before display 트리거로 주입되므로, 그 트리거가 없던 사이트엔 이 모듈이
   * 만든 본문 자체가 없다.
   *
   * 설정 row 유무를 기준으로 삼지 않는 이유: 테이블이 없는 모듈이라
   * 기존 사이트에 파일만 올린 경우 moduleInstall() 이 불리지 않고, 운영자가
   * 설정을 저장한 적이 없으면 row 가 없다 — 잘 쓰던 사이트를 신규 설치로
   * 오판한다.
   */
  private function wasActiveBeforeThisUpdate(): bool
  {
    foreach (ModuleModel::getTriggers('display', 'before') as $trigger) {
      if (($trigger->module ?? null) === 'oembed') {
        return true;
      }
    }
    return false;
  }

  public function recompileCache()
  {
  }
}
