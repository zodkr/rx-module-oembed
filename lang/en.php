<?php

$lang->cmd_oembed = 'oEmbed';
$lang->cmd_oembed_general_config = 'General';
$lang->cmd_oembed_providers = 'Providers';

$lang->oembed_compatible_mode = 'Compatible mode (preview markup)';
$lang->oembed_compatible_mode_desc = 'Render cards using the same class names as the preview module (<code>preview_card_*</code>) so that legacy stylesheets keep working.';

$lang->oembed_provider_name = 'Provider';
$lang->oembed_provider_type = 'Type';
$lang->oembed_provider_oembed = 'oEmbed';
$lang->oembed_provider_hosts = 'Hosts';
$lang->oembed_provider_status = 'Status';

$lang->oembed_provider_type_multimedia = 'Multimedia';
$lang->oembed_provider_type_social = 'Social';

$lang->oembed_preview_active_warning = 'The preview module is currently active. Running both preview and oembed at the same time causes both modules to handle the same actions and conflicts. Please disable the preview module from System → Module management before retrying.';

$lang->oembed_refresh_providers = 'Refresh provider cache';
$lang->oembed_refresh_providers_desc = 'Click after dropping a new PHP file into providers/ to apply changes immediately.';
$lang->oembed_providers_help = 'Checked providers are disabled and will be skipped at paste time.';
$lang->oembed_host_whitelisted = 'Host is registered in the iframe whitelist.';
$lang->oembed_host_not_whitelisted = 'Not yet whitelisted. Will be registered automatically on the next paste.';

$lang->oembed_preview_disable_guide_title = 'How to disable the preview module';
$lang->oembed_preview_disable_guide_steps = '<ol><li>From the admin sidebar, go to System → Module management.</li><li>Find <strong>Link Preview (preview)</strong> in the installed modules list and press the <strong>Remove</strong> button on its row.</li><li>oembed automatically absorbs the preview module\'s public actions (<code>dispPreviewCard</code> etc.), so external caches, emails, and external links keep working.</li><li>The <code>preview_card_*</code> markup stored in existing posts is also rendered by oembed under the same class names when <em>compatible mode</em> is ON.</li></ol>';
