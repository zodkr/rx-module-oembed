<?php

$lang->cmd_oembed = 'oEmbed';
$lang->cmd_oembed_general_config = '기본 설정';
$lang->cmd_oembed_providers = 'Provider 관리';

$lang->oembed_compatible_mode = 'preview 모듈 호환';
$lang->oembed_compatible_mode_desc = 'preview 모듈로 작성해 두었던 기존 게시물의 카드와 임베드를 그대로 표시합니다. 본문에 저장된 <code>preview_card_*</code>·<code>media_embed_wrapper</code> 마크업이 같은 모양으로 보이도록 게시물 읽기 화면에서 호환 스타일과 후처리 스크립트(인스타그램·페이스북·imgur SDK 자동 로드, lazy iframe 활성화) 를 함께 적용합니다. preview 모듈을 사용한 적 없는 새 사이트라면 꺼두셔도 됩니다.';

$lang->oembed_provider_name = 'Provider';
$lang->oembed_provider_type = '유형';
$lang->oembed_provider_oembed = 'oEmbed';
$lang->oembed_provider_hosts = '호스트';
$lang->oembed_provider_status = '상태';
$lang->oembed_provider_enabled = '사용';

$lang->oembed_provider_type_multimedia = '멀티미디어';
$lang->oembed_provider_type_social = 'SNS';

$lang->oembed_preview_active_warning = 'preview 모듈이 함께 설치되어 있습니다. 두 모듈이 글쓰기 페이지에서 동시에 paste 를 처리하려 해 임베드가 중복되거나 어긋날 수 있으니, preview 모듈을 제거해 주세요.';

$lang->oembed_refresh_providers = 'Provider 목록 다시 불러오기';
$lang->oembed_refresh_providers_desc = '새 Provider 파일을 추가했을 때 캐시 갱신을 위해 사용해주세요.';
$lang->oembed_providers_help = '체크한 Provider 만 사용합니다. 체크를 해제한 Provider 의 URL 은 자동 임베드되지 않습니다.';
$lang->oembed_host_whitelisted = '외부 멀티미디어 허용 목록에 등록되어 있습니다.';
$lang->oembed_host_not_whitelisted = '아직 허용되지 않은 호스트입니다. 시스템 → 설정 → 보안 → 외부 멀티미디어 허용에 직접 추가하셔야 게시물에 임베드가 표시됩니다.';

$lang->oembed_missing_hosts_title = '허용이 필요한 외부 호스트';
$lang->oembed_missing_hosts_intro = '아래 호스트가 <strong>외부 멀티미디어 허용 목록</strong>에 없어, 게시물에 삽입된 iframe 이 화면에 표시되지 않습니다. 아래 텍스트를 복사하셔서 시스템 → 설정 → 보안 → <em>외부 멀티미디어 허용</em> 에 추가해 주세요. (보안상 이 목록은 모듈이 자동으로 변경하지 않습니다.)';
$lang->oembed_open_security_config = '시스템 보안 설정 열기';

$lang->oembed_preview_disable_guide_title = 'preview 모듈 제거 안내';
$lang->oembed_preview_disable_guide_steps = '<p>preview 모듈을 제거해 주세요. 이미 작성된 게시물의 <code>preview_card_*</code> 카드와 <code>media_embed_wrapper</code> 임베드는 <em>호환 모드</em> 가 켜져 있을 때 oembed 가 같은 모양으로 출력하므로 게시물이 깨지지 않습니다.</p>';
