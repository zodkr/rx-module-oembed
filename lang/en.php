<?php

$lang->cmd_oembed = 'oEmbed';
$lang->cmd_oembed_general_config = 'General';
$lang->cmd_oembed_providers = 'Providers';

$lang->oembed_compatible_mode = 'Preview module compatibility';
$lang->oembed_compatible_mode_desc = 'Keeps cards and embeds saved by the preview module rendering correctly. The legacy <code>preview_card_*</code> and <code>media_embed_wrapper</code> markup is emitted under the same class names, and external calls such as <code>dispPreviewCard</code> are handled by oembed. Safe to turn off on a new site that has never used preview.';

$lang->oembed_provider_name = 'Provider';
$lang->oembed_provider_type = 'Type';
$lang->oembed_provider_oembed = 'oEmbed';
$lang->oembed_provider_hosts = 'Hosts';
$lang->oembed_provider_status = 'Status';
$lang->oembed_provider_enabled = 'Enabled';

$lang->oembed_provider_type_multimedia = 'Multimedia';
$lang->oembed_provider_type_social = 'Social';

$lang->oembed_preview_active_warning = 'The preview module is also installed. Running both modules at once causes them to handle the same actions and conflict, so please remove the preview module from System → Module management.';

$lang->oembed_refresh_providers = 'Reload provider list';
$lang->oembed_refresh_providers_desc = 'Use this to refresh the cache after adding a new provider file.';
$lang->oembed_providers_help = 'Only checked providers are used. URLs from unchecked providers are not auto-embedded.';
$lang->oembed_host_whitelisted = 'Allowed in System → Security → External media whitelist.';
$lang->oembed_host_not_whitelisted = 'Not yet allowed. Add it manually under System → Security → External media whitelist; otherwise embedded iframes will be filtered out at render time.';

$lang->oembed_missing_hosts_title = 'External hosts pending approval';
$lang->oembed_missing_hosts_intro = 'The following hosts are not in the <strong>external media whitelist</strong>, so embedded iframes will be filtered out when posts are rendered. Copy the list and add it under System → Security → <em>External media whitelist</em>. (oembed never modifies this list automatically for security reasons.)';
$lang->oembed_open_security_config = 'Open System Security Settings';

$lang->oembed_preview_disable_guide_title = 'How to disable the preview module';
$lang->oembed_preview_disable_guide_steps = '<ol><li>From the admin sidebar, go to System → Module management.</li><li>Find <strong>Link Preview (preview)</strong> in the installed modules list and press the <strong>Remove</strong> button on its row.</li><li>oembed automatically absorbs the preview module\'s public actions (<code>dispPreviewCard</code> etc.), so external caches, emails, and external links keep working.</li><li>The <code>preview_card_*</code> markup stored in existing posts is also rendered by oembed under the same class names when <em>compatible mode</em> is ON.</li></ol>';
