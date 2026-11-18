<?php

namespace Rhymix\Modules\Oembed\Controllers;

use Rhymix\Framework\Filters\MediaFilter;
use Rhymix\Framework\Session;
use Rhymix\Modules\Oembed\Models\Config as ConfigModel;
use Rhymix\Modules\Oembed\Models\LegacyCleaner;
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
    $config = ConfigModel::getConfig();
    $enabledProviderKeys = is_array($config->enabled_providers ?? null) ? $config->enabled_providers : [];

    $hostStatus = [];
    $missingHosts = [];
    foreach ($providers as $key => $provider) {
      // 호스트 뱃지는 비활성 provider 도 그려서 켜기 전에 등록 상태를 미리 확인할 수 있게 둔다.
      // 다만 상단의 "허용이 필요한 외부 호스트" 안내는 실제로 사용 체크된 provider 만 모은다 —
      // 사이트 정책상 쓰지 않는 provider 의 호스트까지 등록을 권하지 않는다.
      $isEnabled = in_array($key, $enabledProviderKeys, true);
      foreach ($provider->getEmbedHosts() as $host) {
        $isWhitelisted = MediaFilter::matchWhitelist('https://' . $host . '/');
        $hostStatus[$key][$host] = $isWhitelisted;
        if (!$isWhitelisted && $isEnabled) {
          $missingHosts[$host] = true;
        }
      }
    }

    Context::set('oembed_config', $config);
    Context::set('oembed_preview_active', ConfigModel::isPreviewModuleActive());
    Context::set('oembed_providers', $providers);
    Context::set('oembed_host_whitelist', $hostStatus);
    Context::set('oembed_missing_hosts', array_keys($missingHosts));
    $this->setTemplateFile('providers');
  }

  /**
   * 이전 버전 본문 정리 화면. 실제 작업은 화면의 스크립트
   * (tpl/js/_admin_cleanup.js) 가 procOembedAdminCleanupLegacy 를 반복 호출해
   * 진행한다.
   */
  public function dispOembedAdminCleanup()
  {
    if (!$this->user->isAdmin()) {
      return new \BaseObject(-1, 'admin.msg_is_not_administrator');
    }
    Context::set('oembed_cleanup_targets', LegacyCleaner::getTargets());
    Context::loadFile(array('./modules/oembed/tpl/js/_admin_cleanup.js', 'body', '', null), true);
    $this->setTemplateFile('cleanup');
  }

  /**
   * 게시물 / 댓글 본문에서 legacy wrapper 의 editor_component 속성을 DB 에서
   * 제거한다. 한 번 호출이 처리하는 양은 LegacyCleaner::run 의 시간 예산만큼
   * 이고, 응답의 cursor 를 다음 호출에 넘겨 이어 간다.
   *
   *   step=init → 대상별 전체 행 수 (진행률 분모)
   *   step=run  → target, cursor 를 받아 한 묶음 처리
   *
   * confirm_backup=Y 가 없으면 거부한다. 화면의 체크박스가 같은 값을 보내며,
   * 서버에서도 확인해 두면 백업 경고를 건너뛴 직접 호출로는 실행되지 않는다.
   */
  public function procOembedAdminCleanupLegacy()
  {
    Context::setResponseMethod('JSON');
    if (!$this->user->isAdmin()) {
      return new \BaseObject(-1, 'admin.msg_is_not_administrator');
    }
    if (Context::get('confirm_backup') !== 'Y') {
      return new \BaseObject(-1, 'msg_oembed_cleanup_confirm_required');
    }

    // 요청이 연달아 이어지는 동안 세션 잠금을 쥐고 있으면 같은 관리자의 다른
    // 탭이 전부 대기하게 된다. 코어의 모듈 업데이트와 같은 방식으로 풀어 둔다.
    Session::close();
    try {
      $step = (string) Context::get('step');
      if ($step === 'init') {
        $totals = [];
        foreach (LegacyCleaner::getTargets() as $target) {
          $totals[$target] = LegacyCleaner::countRows($target);
        }
        $this->add('totals', $totals);
      } elseif ($step === 'run') {
        $target = (string) Context::get('target');
        if (!LegacyCleaner::isTarget($target)) {
          return new \BaseObject(-1, 'msg_invalid_request');
        }
        foreach (LegacyCleaner::run($target, (int) Context::get('cursor')) as $key => $value) {
          $this->add($key, $value);
        }
        $this->add('target', $target);
      } else {
        return new \BaseObject(-1, 'msg_invalid_request');
      }
    } catch (\Exception $e) {
      return new \BaseObject(-1, $e->getMessage());
    } finally {
      Session::start();
    }
  }

  public function procOembedAdminInsertConfig()
  {
    $config = ConfigModel::getConfig();
    $vars = Context::getRequestVars();
    // 두 어드민 폼 모두 같은 act(procOembedAdminInsertConfig) 로 진입하므로
    // hidden 'screen' 필드로 어느 화면에서 왔는지 식별한다. 과거에는
    // Context::get('act') 로 분기했는데, 그 시점의 act 는 핸들러 자기
    // 이름이라 어떤 분기도 참이 되지 않아 설정이 무시되는 버그가 있었다.
    $screen = (string) Context::get('screen');

    if ($screen === 'config') {
      $config->compatible_mode = (($vars->compatible_mode ?? 'N') === 'Y') ? 'Y' : 'N';
      // Install 의 자동 판정을 운영자가 뒤집을 수 있게 한다 (예: 신규 설치
      // 사이트에 이전 버전 시절 게시물을 이관해 온 경우).
      $config->legacy_wrappers = (($vars->legacy_wrappers ?? 'N') === 'Y') ? 'Y' : 'N';
      $skin = trim((string) ($vars->skin ?? ''));
      if ($skin !== '' && is_dir($this->module_path . 'skins/' . $skin)) {
        $config->skin = $skin;
      }
    } elseif ($screen === 'providers') {
      // 폼은 enabled_providers[] (= 사용 체크) 만 전송한다.
      // 화이트리스트 모델: 명시적으로 enable 된 provider 만 저장하고,
      // 새 provider 파일이 추가돼도 자동으로 동작하지 않도록 한다.
      // array_intersect 로 알 수 없는 키(클라이언트 위변조) 는 거른다.
      $submitted = is_array($vars->enabled_providers ?? null)
        ? array_map('strval', $vars->enabled_providers)
        : [];
      $allKeys = array_keys(Registry::getProviders(false));
      $config->enabled_providers = array_values(array_intersect($allKeys, $submitted));
      // 레거시 키 정리 — moduleUpdate 에서 한 번 마이그레이션됐어도
      // 폼 저장 시점에 다시 제거해 두면 데이터 모델이 단순해진다.
      unset($config->disabled_providers);
      Registry::flush();
    } else {
      return new \BaseObject(-1, 'msg_invalid_request');
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
