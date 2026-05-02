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
      @endphp
      @foreach ($oembed_providers as $key => $provider)
        @php
          $isDisabled = in_array($key, $disabledProviders, true);
          $typeLabel = $provider->type === 'multimedia'
            ? $lang->oembed_provider_type_multimedia
            : $lang->oembed_provider_type_social;
        @endphp
        <tr>
          <td>
            <input type="checkbox" name="disabled_providers[]" value="{{ $key }}" {{ $isDisabled ? 'checked' : '' }} />
          </td>
          <td><strong>{{ $provider->name }}</strong> <small class="x_text-muted">({{ $key }})</small></td>
          <td>{{ $typeLabel }}</td>
          <td>{{ $provider->oembed ? 'O' : '-' }}</td>
          <td><small>{{ implode(', ', $provider->hosts) }}</small></td>
        </tr>
      @endforeach
      @if (count($oembed_providers) === 0)
        <tr><td colspan="5" class="x_text-center x_text-muted">no providers found</td></tr>
      @endif
    </tbody>
  </table>

  <p class="x_help-block">체크된 provider 는 비활성화됩니다. 비활성화된 provider 는 paste 시점에 매칭에서 제외됩니다.</p>

  <button type="submit" class="x_btn x_btn-primary">{{ $lang->cmd_registration }}</button>
</form>
