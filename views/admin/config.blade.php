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
</ul>

@if (!empty($oembed_preview_active) || !empty($oembed_preview_conflict))
  <div class="x_alert x_alert-warning oembed-alert">
    {!! $lang->oembed_preview_active_warning !!}
  </div>
  <div class="oembed-guide">
    <h3>{{ $lang->oembed_preview_disable_guide_title }}</h3>
    {!! $lang->oembed_preview_disable_guide_steps !!}
  </div>
@endif

<form action="{{ getUrl() }}" method="post" id="oembedConfigForm">
  <input type="hidden" name="module" value="admin" />
  <input type="hidden" name="act" value="procOembedAdminInsertConfig" />
  <input type="hidden" name="screen" value="config" />
  <input type="hidden" name="success_return_url" value="{{ getCurrentPageUrl() }}" />

  <section class="section">
    <h2>{{ $lang->cmd_oembed_general_config }}</h2>
  </section>

  <div class="x_page-content">
    <div class="oembed-row">
      <label class="oembed-field-label" for="oembed-skin">{{ $lang->oembed_skin }}</label>
      <select id="oembed-skin" name="skin" class="oembed-select">
        @php $currentSkin = $oembed_config->skin ?? 'default'; @endphp
        @foreach ($oembed_skin_list as $skinKey => $skinInfo)
          <option value="{{ $skinKey }}" {{ $currentSkin === $skinKey ? 'selected' : '' }}>
            {{ $skinInfo->title ?? $skinKey }}
          </option>
        @endforeach
      </select>
      <p class="oembed-help">{{ $lang->oembed_skin_desc }}</p>
    </div>

    <div class="oembed-row">
      <label class="oembed-checkbox">
        <input type="checkbox" name="compatible_mode" value="Y"
               {{ ($oembed_config->compatible_mode ?? 'Y') === 'Y' ? 'checked' : '' }} />
        <span>{{ $lang->oembed_compatible_mode }}</span>
      </label>
      <p class="oembed-help">{!! $lang->oembed_compatible_mode_desc !!}</p>
    </div>
  </div>

  <div class="btnArea x_clearfix">
    <button type="submit" class="x_btn x_btn-primary x_pull-right">{{ $lang->cmd_registration }}</button>
  </div>
</form>

<style>
  .oembed-alert { margin-bottom: 12px; }
  .oembed-guide {
    border: 1px solid #f5c6cb; border-radius: 6px; padding: 16px 20px;
    margin-bottom: 20px; background: #fff8f8;
  }
  .oembed-guide h3 { margin-top: 0; }
  .oembed-guide ol { padding-left: 20px; }
  .oembed-guide li { margin-bottom: 6px; }

  .oembed-row { margin-bottom: 16px; }
  .oembed-checkbox {
    display: flex; align-items: flex-start; gap: 8px;
    font-weight: 600; cursor: pointer; line-height: 1.4;
  }
  .oembed-checkbox input { margin-top: 2px; flex: 0 0 auto; }
  .oembed-checkbox span { flex: 1 1 auto; }
  .oembed-help {
    margin: 6px 0 0 26px; color: #666; font-size: 0.92em; line-height: 1.5;
  }
  .oembed-help code {
    padding: 1px 6px; background: #f5f5f5; border-radius: 3px;
    color: #c7254e; font-size: 0.92em;
  }

  .oembed-field-label {
    display: block; font-weight: 600; margin-bottom: 6px; line-height: 1.4;
  }
  .oembed-select {
    width: 100%; max-width: 360px; padding: 6px 10px;
    border: 1px solid #d0d7de; border-radius: 4px; background: #fff;
  }
</style>
