<?php

$lang->cmd_oembed = 'oEmbed';
$lang->cmd_oembed_general_config = 'General';
$lang->cmd_oembed_providers = 'Providers';

$lang->oembed_compatible_mode = 'Preview module compatibility';
$lang->oembed_compatible_mode_desc = 'Keeps cards and embeds authored under the preview module rendering correctly. Adds compatible styles and a post-processing script (auto-loading Instagram / Facebook / Imgur SDKs and activating lazy iframes) on post-view pages so that legacy <code>preview_card_*</code> / <code>media_embed_wrapper</code> markup keeps its original look. Safe to turn off on a new site that has never used preview.';

$lang->oembed_provider_name = 'Provider';
$lang->oembed_provider_type = 'Type';
$lang->oembed_provider_oembed = 'oEmbed';
$lang->oembed_provider_hosts = 'Hosts';
$lang->oembed_provider_status = 'Status';
$lang->oembed_provider_enabled = 'Enabled';

$lang->oembed_provider_type_multimedia = 'Multimedia';
$lang->oembed_provider_type_social = 'Social';

$lang->oembed_preview_active_warning = 'The preview module is also installed. Both modules will try to handle paste events on write pages, which can produce duplicate or mismatched embeds. Please remove the preview module from System → Module management.';

$lang->oembed_refresh_providers = 'Reload provider list';
$lang->oembed_refresh_providers_desc = 'Use this to refresh the cache after adding a new provider file.';
$lang->oembed_providers_help = 'Only checked providers are used. URLs from unchecked providers are not auto-embedded.';
$lang->oembed_host_whitelisted = 'Allowed in System → Security → External media whitelist.';
$lang->oembed_host_not_whitelisted = 'Not yet allowed. Add it manually under System → Security → External media whitelist; otherwise embedded iframes will be filtered out at render time.';

$lang->oembed_missing_hosts_title = 'External hosts pending approval';
$lang->oembed_missing_hosts_intro = 'The following hosts are not in the <strong>external media whitelist</strong>, so embedded iframes will be filtered out when posts are rendered. Copy the list and add it under System → Security → <em>External media whitelist</em>. (oembed never modifies this list automatically for security reasons.)';
$lang->oembed_open_security_config = 'Open System Security Settings';

$lang->oembed_preview_disable_guide_title = 'How to disable the preview module';
$lang->oembed_preview_disable_guide_steps = '<ol><li>From the admin sidebar, go to System → Module management.</li><li>Find <strong>Link Preview (preview)</strong> in the installed modules list and press the <strong>Remove</strong> button on its row.</li><li>Existing posts that contain <code>preview_card_*</code> cards and <code>media_embed_wrapper</code> embeds keep their original look while <em>compatible mode</em> is ON, so posts will not break.</li></ol>';
