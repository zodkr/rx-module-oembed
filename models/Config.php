<?php

namespace Rhymix\Modules\Oembed\Models;

use ModuleController;
use ModuleModel;

class Config
{
  protected static ?object $cache = null;

  public const DEFAULT_SKIN = 'default';

  /**
   * 모듈에 번들로 포함되어 있는 기본 provider 목록. 신규 설치 시 이
   * 목록만 활성화 상태로 시드되고, providers/ 디렉터리에 추가된 새
   * 파일은 운영자가 어드민에서 명시적으로 켜야만 동작한다 (보안 정책).
   */
  public const BUNDLED_PROVIDERS = ['Youtube', 'Facebook', 'Instagram', 'X', 'Reddit'];

  public static function getConfig(): object
  {
    if (self::$cache === null) {
      $config = ModuleModel::getModuleConfig('oembed');
      self::$cache = is_object($config) ? $config : new \stdClass();
      self::$cache->compatible_mode = self::$cache->compatible_mode ?? 'N';
      self::$cache->skin = is_string(self::$cache->skin ?? null) && self::$cache->skin !== ''
        ? self::$cache->skin
        : self::DEFAULT_SKIN;
      // 화이트리스트 모델: enabled_providers 에 들어있는 것만 활성.
      // 값이 없으면(=신규 설치 직후 또는 마이그레이션 전) 번들 기본값으로
      // 폴백한다. 실제 마이그레이션 (disabled_providers → enabled_providers)
      // 은 Install::moduleUpdate 에서 한 번 수행한다.
      self::$cache->enabled_providers = is_array(self::$cache->enabled_providers ?? null)
        ? self::$cache->enabled_providers
        : self::BUNDLED_PROVIDERS;
    }
    return self::$cache;
  }

  public static function setConfig(object $config): object
  {
    $oModuleController = ModuleController::getInstance();
    $result = $oModuleController->insertModuleConfig('oembed', $config);
    if ($result->toBool()) {
      self::$cache = $config;
    }
    return $result;
  }

  /**
   * 호환 모드는 preview 시절 본문에 저장된 카드/임베드를 *게시물 읽기*
   * 화면에서 그대로 보이게 하기 위한 옵션이다.
   *
   * ON 일 때 EventHandlers 가 글 보기 페이지에 _render.js 를 주입해
   * .instagram-media / .fb-post / .twitter-tweet 등 레거시 임베드의
   * SDK 를 자동 로드하고, iframe[data-src] 도 활성화한다.
   *
   * 새로 만드는 카드 마크업 자체는 토글과 무관하게 항상 같은 형태이며,
   * 이 옵션은 신규 마크업의 클래스명에는 영향을 주지 않는다.
   */
  public static function isCompatibleMode(): bool
  {
    return self::getConfig()->compatible_mode !== 'N';
  }

  /**
   * v1.0.4 이하가 남긴 `editor_component="oembed"` wrapper 를 렌더 전에
   * 정규화할지 여부 (LegacyWrapper / EventHandlers 참고).
   *
   * 이전 버전에서 업데이트한 사이트에서만 켜진다. 값은 Install 이 한 번
   * 결정해 `legacy_wrappers` 키 ('Y' / 'N') 로 저장한다 — 신규 설치는 'N'.
   * 트리거 등록 자체를 조건부로 할 수는 없어서 (conf/module.xml 에 없는
   * 트리거는 코어가 "업데이트 필요" 로 표시하고 업데이트 때 삭제한다),
   * 핸들러가 이 값을 보고 바로 빠져나가는 방식으로 게이팅한다.
   *
   * 키가 아직 없으면 켜진 것으로 본다. 키 없이 이 핸들러가 불리는 경우는
   * "legacy 트리거는 이미 등록됐는데 판정 전" 인 사이트뿐이고 (판정 로직이
   * 생기기 전 커밋으로 업데이트한 사이트), 그런 사이트는 전부 업데이트한
   * 사이트다. 신규 설치는 트리거가 등록되는 바로 그 업데이트에서 판정도
   * 함께 끝나므로 키 없는 상태로 핸들러가 불리지 않는다.
   *
   * getConfig() 에서 이 키의 기본값을 채우지 않는 이유: getConfig() 가 돌려주는
   * 객체는 ModuleModel 의 설정 캐시와 같은 인스턴스라, 기본값을 채우면
   * Install 의 "아직 판정 전인가" 검사 (isset) 가 깨진다.
   */
  public static function shouldNormalizeLegacyWrappers(): bool
  {
    return (self::getConfig()->legacy_wrappers ?? 'Y') !== 'N';
  }

  /**
   * 현재 활성 카드 스킨 이름. CardRenderer 와 EventHandlers 가 공유한다.
   * 운영자가 어드민에서 선택한 스킨이 실제 디렉터리에 없으면 'default' 폴백.
   */
  public static function getSkin(): string
  {
    $skin = (string) (self::getConfig()->skin ?? self::DEFAULT_SKIN);
    if ($skin === '' || !is_dir(\RX_BASEDIR . 'modules/oembed/skins/' . $skin)) {
      return self::DEFAULT_SKIN;
    }
    return $skin;
  }

  /**
   * preview 모듈이 디스크에 존재하면(=활성화 상태) true.
   * Rhymix 의 모듈 관리 화면에서 모듈을 제거하면 디렉터리 자체가 삭제되므로
   * 디렉터리 존재 여부만으로 활성화 여부를 판단해도 충분하다.
   */
  public static function isPreviewModuleActive(): bool
  {
    return is_file(\RX_BASEDIR . 'modules/preview/conf/info.xml');
  }
}
