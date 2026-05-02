<?php

namespace Rhymix\Modules\Oembed\Models;

use ModuleController;
use ModuleModel;

class Config
{
  protected static ?object $cache = null;

  public static function getConfig(): object
  {
    if (self::$cache === null) {
      $config = ModuleModel::getModuleConfig('oembed');
      self::$cache = is_object($config) ? $config : new \stdClass();
      self::$cache->compatible_mode = self::$cache->compatible_mode ?? 'Y';
      self::$cache->disabled_providers = is_array(self::$cache->disabled_providers ?? null)
        ? self::$cache->disabled_providers
        : [];
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
   * 호환 모드는 preview 모듈이 만들어 놓은 기존 카드/임베드와 외부 호출
   * (dispPreviewCard 등)을 oembed 가 그대로 처리하기 위한 옵션이다.
   *
   * ON 일 때:
   *   - 신규 카드도 preview 의 정확한 클래스명(preview_card_text_container,
   *     preview_card_desc 등)으로 출력 → preview 시절 본문과 동일 CSS 적용
   *   - 글 보기 페이지에서 _render.js 가 주입되어 .instagram-media 등
   *     레거시 임베드의 SDK 가 자동 로드됨
   * OFF 일 때:
   *   - 신규 카드는 oembed_card_* 클래스만 사용
   *   - 레거시 임베드 후처리 JS 미주입
   */
  public static function isCompatibleMode(): bool
  {
    return self::getConfig()->compatible_mode !== 'N';
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
