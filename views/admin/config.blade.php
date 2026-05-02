<div class="x_page-header">
  <h1>{{ $lang->cmd_oembed }}</h1>
</div>

@if (!empty($oembed_preview_active) || !empty($oembed_preview_conflict))
  <div class="x_alert x_alert-warning" style="margin-bottom: 12px;">
    {!! $lang->oembed_preview_active_warning !!}
  </div>
  <div class="x_panel" style="border:1px solid #f5c6cb; border-radius:6px; padding:12px 16px; margin-bottom: 16px; background:#fff8f8;">
    <h3 style="margin-top:0;">{{ $lang->oembed_preview_disable_guide_title }}</h3>
    {!! $lang->oembed_preview_disable_guide_steps !!}
    <a class="x_btn x_btn-primary" href="{{ getUrl('', 'module', 'admin', 'act', 'dispModuleAdminContent') }}">
      {{ $lang->oembed_preview_disable_guide_title }} →
    </a>
  </div>
@endif

<ul class="x_nav x_nav-tabs">
  <li @class(['x_active' => $act === 'dispOembedAdminConfig'])>
    <a href="{{ getUrl('', 'module', 'admin', 'act', 'dispOembedAdminConfig') }}">{{ $lang->cmd_oembed_general_config }}</a>
  </li>
  <li @class(['x_active' => $act === 'dispOembedAdminProviders'])>
    <a href="{{ getUrl('', 'module', 'admin', 'act', 'dispOembedAdminProviders') }}">{{ $lang->cmd_oembed_providers }}</a>
  </li>
</ul>

<form action="{{ getUrl() }}" method="post" class="x_form-horizontal">
  <input type="hidden" name="module" value="admin" />
  <input type="hidden" name="act" value="procOembedAdminInsertConfig" />
  <input type="hidden" name="success_return_url" value="{{ getCurrentPageUrl() }}" />

  <fieldset>
    <legend>{{ $lang->cmd_oembed_general_config }}</legend>

    <div class="x_form-group">
      <label class="x_control-label">
        <input type="checkbox" name="compatible_mode" value="Y"
               {{ ($oembed_config->compatible_mode ?? 'Y') === 'Y' ? 'checked' : '' }} />
        {{ $lang->oembed_compatible_mode }}
      </label>
      <p class="x_help-block">{!! $lang->oembed_compatible_mode_desc !!}</p>
    </div>
  </fieldset>

  <button type="submit" class="x_btn x_btn-primary">{{ $lang->cmd_registration }}</button>
</form>
