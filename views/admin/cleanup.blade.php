<div class="x_page-header">
  <h1>{{ $lang->cmd_oembed }}</h1>
</div>

<ul class="x_nav x_nav-tabs">
  <li @class(['x_active' => $act === 'dispOembedAdminConfig'])>
    <a href="{{ getUrl('', 'module', 'admin', 'act', 'dispOembedAdminConfig') }}">{{ $lang->cmd_oembed_general_config }}</a>
  </li>
  <li @class(['x_active' => $act === 'dispOembedAdminProviders'])>
    <a href="{{ getUrl('', 'module', 'admin', 'act', 'dispOembedAdminProviders') }}">{{ $lang->cmd_oembed_providers }}</a>
  </li>
  <li @class(['x_active' => $act === 'dispOembedAdminCleanup'])>
    <a href="{{ getUrl('', 'module', 'admin', 'act', 'dispOembedAdminCleanup') }}">{{ $lang->cmd_oembed_cleanup }}</a>
  </li>
</ul>

<section class="section">
  <h2>{{ $lang->cmd_oembed_cleanup }}</h2>
</section>

<div class="x_page-content">
  <p class="oembed-cleanup-intro">{!! $lang->oembed_cleanup_intro !!}</p>

  <div class="oembed-cleanup-warning" role="alert">
    <strong class="oembed-cleanup-warning-title">{{ $lang->oembed_cleanup_warning_title }}</strong>
    {!! $lang->oembed_cleanup_warning_body !!}
  </div>

  {{-- 진행 스크립트(tpl/js/_admin_cleanup.js) 가 쓰는 문구는 data-* 로 넘긴다. --}}
  <div id="oembedCleanup"
       data-targets="{{ implode(',', $oembed_cleanup_targets) }}"
       data-label-start="{{ $lang->oembed_cleanup_start }}"
       data-label-resume="{{ $lang->oembed_cleanup_resume }}"
       data-msg-preparing="{{ $lang->oembed_cleanup_status_preparing }}"
       data-msg-running="{{ $lang->oembed_cleanup_status_running }}"
       data-msg-stopping="{{ $lang->oembed_cleanup_status_stopping }}"
       data-msg-stopped="{{ $lang->oembed_cleanup_status_stopped }}"
       data-msg-done="{{ $lang->oembed_cleanup_status_done }}"
       data-msg-error="{{ $lang->oembed_cleanup_status_error }}">

    <label class="oembed-checkbox oembed-cleanup-confirm">
      <input type="checkbox" id="oembedCleanupConfirm" />
      <span>{{ $lang->oembed_cleanup_confirm }}</span>
    </label>

    <div class="oembed-cleanup-actions">
      <button type="button" class="x_btn x_btn-danger" id="oembedCleanupStart" disabled>{{ $lang->oembed_cleanup_start }}</button>
      <button type="button" class="x_btn" id="oembedCleanupStop" hidden>{{ $lang->oembed_cleanup_stop }}</button>
    </div>

    <div class="oembed-cleanup-progress" hidden>
      <div class="oembed-cleanup-bar" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0"><span></span></div>
      <p class="oembed-cleanup-status" aria-live="polite"></p>
      <p class="oembed-cleanup-error" hidden></p>

      <table class="x_table oembed-cleanup-table">
        <thead>
          <tr>
            <th scope="col">{{ $lang->oembed_cleanup_col_target }}</th>
            <th scope="col">{{ $lang->oembed_cleanup_col_total }}</th>
            <th scope="col">{{ $lang->oembed_cleanup_col_scanned }}</th>
            <th scope="col">{{ $lang->oembed_cleanup_col_updated }}</th>
            <th scope="col">{{ $lang->oembed_cleanup_col_skipped }}</th>
          </tr>
        </thead>
        <tbody>
          @foreach ($oembed_cleanup_targets as $target)
            <tr data-target="{{ $target }}">
              <th scope="row">{{ $lang->{'oembed_cleanup_target_' . $target} }}</th>
              <td data-cell="total">-</td>
              <td data-cell="scanned">0</td>
              <td data-cell="updated">0</td>
              <td data-cell="skipped">0</td>
            </tr>
          @endforeach
        </tbody>
      </table>
      <p class="oembed-help oembed-cleanup-skipped-help">{{ $lang->oembed_cleanup_skipped_help }}</p>
    </div>
  </div>
</div>

<style>
  .oembed-cleanup-intro { margin: 0 0 16px; line-height: 1.6; }
  .oembed-cleanup-intro code, .oembed-cleanup-warning code {
    padding: 1px 6px; background: #f5f5f5; border-radius: 3px;
    color: #c7254e; font-size: 0.92em;
  }

  .oembed-cleanup-warning {
    border: 1px solid #f5c6cb; border-left: 4px solid #d93025; border-radius: 6px;
    padding: 16px 20px; margin-bottom: 20px; background: #fff8f8; line-height: 1.6;
  }
  .oembed-cleanup-warning-title { display: block; margin-bottom: 8px; color: #b3261e; font-size: 1.08em; }
  .oembed-cleanup-warning ul { margin: 0; padding-left: 20px; }
  .oembed-cleanup-warning li { margin-bottom: 6px; }
  .oembed-cleanup-warning li:last-child { margin-bottom: 0; }

  .oembed-checkbox {
    display: flex; align-items: flex-start; gap: 8px;
    font-weight: 600; cursor: pointer; line-height: 1.4;
  }
  .oembed-checkbox input { margin-top: 2px; flex: 0 0 auto; }
  .oembed-checkbox span { flex: 1 1 auto; }
  .oembed-cleanup-actions { display: flex; gap: 8px; margin: 14px 0 0; }

  /* 어드민 CSS 의 .x_btn { display: inline-block } 등이 hidden 속성의 기본
     display: none 을 이기므로 명시적으로 다시 숨긴다. */
  #oembedCleanup [hidden] { display: none !important; }

  .oembed-cleanup-progress { margin-top: 24px; }
  .oembed-cleanup-bar {
    height: 12px; border-radius: 6px; background: #e9ecef; overflow: hidden;
  }
  .oembed-cleanup-bar > span {
    display: block; width: 0; height: 100%; background: #1a73e8; transition: width 0.2s ease;
  }
  .oembed-cleanup-status { margin: 10px 0 0; font-weight: 600; }
  .oembed-cleanup-error { margin: 6px 0 0; color: #b3261e; word-break: break-word; }
  .oembed-cleanup-table { width: 100%; max-width: 640px; margin-top: 16px; }
  .oembed-cleanup-table th, .oembed-cleanup-table td { padding: 8px 10px; text-align: right; }
  .oembed-cleanup-table th:first-child { text-align: left; }
  .oembed-help { margin: 10px 0 0; color: #666; font-size: 0.92em; line-height: 1.5; }
</style>
