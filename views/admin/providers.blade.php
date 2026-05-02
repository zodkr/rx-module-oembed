<div class="x_page-header">
  <h1>{{ $lang->cmd_oembed }}</h1>
</div>

@if (!empty($oembed_preview_active) || !empty($oembed_preview_conflict))
  <div class="x_alert x_alert-warning">
    {!! $lang->oembed_preview_active_warning !!}
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

@php
  $missingHosts = $oembed_missing_hosts ?? [];
@endphp
@if (count($missingHosts) > 0)
  <div class="x_panel" style="border:1px solid #f5c6cb; border-radius:6px; padding:12px 16px; margin-bottom: 16px; background:#fff8f8;">
    <h3 style="margin-top:0;">{{ $lang->oembed_missing_hosts_title }}</h3>
    <p>{!! $lang->oembed_missing_hosts_intro !!}</p>
    <textarea readonly rows="{{ min(count($missingHosts) + 1, 12) }}" class="x_full-width" onclick="this.select();" style="font-family: monospace; width: 100%; padding: 8px;">{{ implode("\n", $missingHosts) }}</textarea>
    <a class="x_btn x_btn-primary" href="{{ getUrl('', 'module', 'admin', 'act', 'dispAdminConfigSecurity') }}#mediafilter_whitelist" target="_blank" rel="noopener" style="margin-top: 8px;">
      {{ $lang->oembed_open_security_config }} →
    </a>
  </div>
@endif

<form action="{{ getUrl() }}" method="post" style="display:inline-block; margin-bottom: 12px;">
  <input type="hidden" name="module" value="admin" />
  <input type="hidden" name="act" value="procOembedAdminRefreshProviders" />
  <input type="hidden" name="success_return_url" value="{{ getCurrentPageUrl() }}" />
  <button type="submit" class="x_btn x_btn-default">{{ $lang->oembed_refresh_providers }}</button>
  <small class="x_text-muted" style="margin-left: 8px;">{{ $lang->oembed_refresh_providers_desc }}</small>
</form>

<form action="{{ getUrl() }}" method="post">
  <input type="hidden" name="module" value="admin" />
  <input type="hidden" name="act" value="procOembedAdminInsertConfig" />
  <input type="hidden" name="success_return_url" value="{{ getCurrentPageUrl() }}" />

  <table class="x_table x_table-striped">
    <thead>
      <tr>
        <th style="width: 40px;"></th>
        <th>{{ $lang->oembed_provider_name }}</th>
        <th>{{ $lang->oembed_provider_type }}</th>
        <th>{{ $lang->oembed_provider_oembed }}</th>
        <th>{{ $lang->oembed_provider_hosts }}</th>
      </tr>
    </thead>
    <tbody>
      @php
        $disabledProviders = $oembed_config->disabled_providers ?? [];
        $hostStatus = $oembed_host_whitelist ?? [];
      @endphp
      @foreach ($oembed_providers as $key => $provider)
        @php
          $isDisabled = in_array($key, $disabledProviders, true);
          $typeLabel = $provider->type === 'multimedia'
            ? $lang->oembed_provider_type_multimedia
            : $lang->oembed_provider_type_social;
          $providerHosts = $hostStatus[$key] ?? [];
        @endphp
        <tr>
          <td>
            <input type="checkbox" name="disabled_providers[]" value="{{ $key }}" {{ $isDisabled ? 'checked' : '' }} />
          </td>
          <td><strong>{{ $provider->name }}</strong> <small class="x_text-muted">({{ $key }})</small></td>
          <td>{{ $typeLabel }}</td>
          <td>{{ $provider->oembed ? 'O' : '-' }}</td>
          <td>
            @foreach ($provider->hosts as $host)
              @php $isWhitelisted = $providerHosts[$host] ?? false; @endphp
              <span title="{{ $isWhitelisted ? $lang->oembed_host_whitelisted : $lang->oembed_host_not_whitelisted }}"
                    style="display:inline-block; padding:1px 6px; margin:1px 2px 1px 0; border-radius:3px;
                           background:{{ $isWhitelisted ? '#e6f4ea' : '#fce8e6' }};
                           color:{{ $isWhitelisted ? '#1e8e3e' : '#c5221f' }};
                           font-size: 0.85em;">
                {{ $isWhitelisted ? '✓' : '✗' }} {{ $host }}
              </span>
            @endforeach
          </td>
        </tr>
      @endforeach
      @if (count($oembed_providers) === 0)
        <tr><td colspan="5" class="x_text-center x_text-muted">no providers found</td></tr>
      @endif
    </tbody>
  </table>

  <p class="x_help-block">{{ $lang->oembed_providers_help }}</p>

  <button type="submit" class="x_btn x_btn-primary">{{ $lang->cmd_registration }}</button>
</form>
