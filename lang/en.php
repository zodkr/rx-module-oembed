<?php

$lang->cmd_oembed = 'oEmbed';
$lang->cmd_oembed_general_config = 'General';
$lang->cmd_oembed_providers = 'Providers';
$lang->cmd_oembed_cleanup = 'Legacy cleanup';

$lang->oembed_skin = 'Card skin';
$lang->oembed_skin_desc = 'Controls the design of preview cards. New skins placed under modules/oembed/skins/ are picked up automatically. Switching skins applies to newly created cards only — existing cards keep their original look.';

$lang->oembed_compatible_mode = 'Preview module compatibility';
$lang->oembed_compatible_mode_desc = 'Keeps cards and embeds authored under the preview module rendering correctly. Adds compatible styles and a post-processing script (auto-loading Instagram / Facebook / X SDKs and activating lazy iframes) on post-view pages so that legacy <code>preview_card_*</code> / <code>media_embed_wrapper</code> markup keeps its original look. Safe to turn off on a new site that has never used preview.';

$lang->oembed_legacy_wrappers = 'Compatibility with posts from earlier versions';
$lang->oembed_legacy_wrappers_desc = 'Shows embeds and cards in posts and comments written with oEmbed 1.0.4 or earlier, whose body still carries the <code>editor_component="oembed"</code> attribute. Recent Rhymix versions blank any tag with that attribute at output, so when this is on the attribute is removed just before rendering (the stored body is not modified). Turned on automatically for sites updated from an earlier version; new installs start with it off.';

$lang->oembed_cleanup_intro = 'Permanently removes the <code>editor_component="oembed"</code> attribute left in the bodies of posts and comments written with oEmbed 1.0.4 or earlier. Once the cleanup is finished, those embeds and cards render without the <em>Compatibility with posts from earlier versions</em> option.';
$lang->oembed_cleanup_warning_title = 'Back up your database before running this';
$lang->oembed_cleanup_warning_body = '<ul><li>This rewrites post (<code>documents</code>) and comment (<code>comments</code>) bodies directly in the database and <strong>cannot be undone.</strong></li><li>On a site with many posts and comments it <strong>can take a long time.</strong> Do not close this window or navigate away while it is running.</li><li>An error, an interruption or a server problem during the run <strong>can damage or lose post bodies.</strong> A backup is the only way to recover from that, so running this only after a backup is strongly recommended.</li><li>Run it during low-traffic hours if you can.</li></ul>';
$lang->oembed_cleanup_confirm = 'I have backed up the database and read the notes above.';
$lang->oembed_cleanup_start = 'Start cleanup';
$lang->oembed_cleanup_resume = 'Continue';
$lang->oembed_cleanup_stop = 'Stop';
$lang->oembed_cleanup_target_documents = 'Posts';
$lang->oembed_cleanup_target_comments = 'Comments';
$lang->oembed_cleanup_col_target = 'Target';
$lang->oembed_cleanup_col_total = 'Total';
$lang->oembed_cleanup_col_scanned = 'Scanned';
$lang->oembed_cleanup_col_updated = 'Updated';
$lang->oembed_cleanup_col_skipped = 'Held';
$lang->oembed_cleanup_skipped_help = 'Held items needed cleaning but were left untouched because they failed the safety check or changed while the cleanup was running. Running the cleanup again retries them.';
$lang->oembed_cleanup_status_preparing = 'Counting rows…';
$lang->oembed_cleanup_status_running = 'Cleaning up. Do not close this window.';
$lang->oembed_cleanup_status_stopping = 'Stopping after the current batch…';
$lang->oembed_cleanup_status_stopped = 'Stopped. Press Continue to pick up where it left off.';
$lang->oembed_cleanup_status_done = 'Cleanup finished. If nothing was held, you can turn off Compatibility with posts from earlier versions in the General tab.';
$lang->oembed_cleanup_status_error = 'Stopped because of an error. Press Continue to retry from where it stopped.';
$lang->msg_oembed_cleanup_confirm_required = 'Confirm that you have backed up the database before running this.';

$lang->oembed_provider_name = 'Provider';
$lang->oembed_provider_type = 'Type';
$lang->oembed_provider_oembed = 'oEmbed';
$lang->oembed_provider_hosts = 'Hosts';
$lang->oembed_provider_status = 'Status';
$lang->oembed_provider_enabled = 'Enabled';

$lang->oembed_provider_type_multimedia = 'Multimedia';
$lang->oembed_provider_type_social = 'Social';

$lang->oembed_preview_active_warning = 'The preview module is also installed. Both modules will try to handle paste events on write pages, which can produce duplicate or mismatched embeds. Please remove the preview module.';

$lang->oembed_refresh_providers = 'Reload provider list';
$lang->oembed_refresh_providers_desc = 'Use this to refresh the cache after adding a new provider file.';
$lang->oembed_providers_help = 'Only checked providers are used. URLs from unchecked providers are not auto-embedded.';
$lang->oembed_host_whitelisted = 'Allowed in System → Security → External media whitelist.';
$lang->oembed_host_not_whitelisted = 'Not yet allowed. Add it manually under System → Security → External media whitelist; otherwise embedded iframes will be filtered out at render time.';

$lang->oembed_missing_hosts_title = 'External hosts pending approval';
$lang->oembed_missing_hosts_intro = 'The following hosts are not in the <strong>external media whitelist</strong>, so embedded iframes will be filtered out when posts are rendered. Copy the list and add it under System → Security → <em>External media whitelist</em>. (oembed never modifies this list automatically for security reasons.)';
$lang->oembed_open_security_config = 'Open System Security Settings';

$lang->oembed_preview_disable_guide_title = 'About removing the preview module';
$lang->oembed_preview_disable_guide_steps = '<p>Please remove the preview module. Existing posts containing <code>preview_card_*</code> cards and <code>media_embed_wrapper</code> embeds keep their original look while <em>compatible mode</em> is ON, so posts will not break.</p>';
