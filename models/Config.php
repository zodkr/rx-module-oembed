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
   * 호환 모드는 preview 시절 본문에 저장된 카드/임베드를 *게시물 읽기*
   * 화면에서 그대로 보이게 하기 위한 옵션이다.
   *
   * ON 일 때 EventHandlers 가 글 보기 페이지에 _render.js 를 주입해
   * .instagram-media / .fb-post / .imgur-embed-pub 등 레거시 임베드의
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
   * preview 모듈이 디스크에 존재하면(=활성화 상태) true.
   * Rhymix 의 모듈 관리 화면에서 모듈을 제거하면 디렉터리 자체가 삭제되므로
   * 디렉터리 존재 여부만으로 활성화 여부를 판단해도 충분하다.
   */
  public static function isPreviewModuleActive(): bool
  {
    return is_file(\RX_BASEDIR . 'modules/preview/conf/info.xml');
  }
}
