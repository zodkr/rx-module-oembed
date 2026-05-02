<?php

$lang->cmd_oembed = 'oEmbed';
$lang->cmd_oembed_general_config = '기본 설정';
$lang->cmd_oembed_providers = 'Provider 관리';

$lang->oembed_compatible_mode = '호환 모드 (preview 마크업 유지)';
$lang->oembed_compatible_mode_desc = '카드 마크업을 preview 모듈과 동일한 클래스명(<code>preview_card_*</code>)으로 출력합니다. 기존 게시글의 CSS 가 그대로 적용됩니다.';

$lang->oembed_provider_name = 'Provider';
$lang->oembed_provider_type = '유형';
$lang->oembed_provider_oembed = 'oEmbed';
$lang->oembed_provider_hosts = '호스트';
$lang->oembed_provider_status = '상태';
$lang->oembed_provider_enabled = '사용';

$lang->oembed_provider_type_multimedia = '멀티미디어';
$lang->oembed_provider_type_social = 'SNS';

$lang->oembed_preview_active_warning = 'preview 모듈이 활성화되어 있습니다. oembed 모듈과 동시에 활성화하면 두 모듈이 동일한 액션을 처리하려고 시도해 충돌합니다. 시스템 → 모듈 관리 화면에서 preview 모듈을 비활성화한 뒤 다시 시도해 주세요.';

$lang->oembed_refresh_providers = 'Provider 목록 다시 불러오기';
$lang->oembed_refresh_providers_desc = '새 Provider 파일을 추가했을 때 캐시 갱신을 위해 사용해주세요.';
$lang->oembed_providers_help = '체크된 provider 만 활성화됩니다. 체크 해제된 provider 는 paste 시점에 매칭에서 제외됩니다.';
$lang->oembed_host_whitelisted = '시스템 보안 설정의 외부 멀티미디어 허용 목록에 등록되어 있습니다.';
$lang->oembed_host_not_whitelisted = '아직 허용되지 않았습니다. 시스템 → 설정 → 보안 → 외부 멀티미디어 허용 화면에서 직접 추가해야 본문 출력 시 임베드가 표시됩니다.';

$lang->oembed_missing_hosts_title = '승인이 필요한 외부 호스트';
$lang->oembed_missing_hosts_intro = '아래 호스트들이 <strong>외부 멀티미디어 허용 목록</strong>에 등록되어 있지 않아, 본문에 임베드된 iframe 이 출력 시점에 차단됩니다. 텍스트를 복사해 시스템 → 설정 → 보안 → <em>외부 멀티미디어 허용</em> 항목에 추가해 주세요. (oembed 모듈은 보안상의 이유로 이 목록을 자동 갱신하지 않습니다.)';
$lang->oembed_open_security_config = '시스템 보안 설정 열기';

$lang->oembed_preview_disable_guide_title = 'preview 모듈을 비활성화하는 방법';
$lang->oembed_preview_disable_guide_steps = '<ol><li>관리자 화면 좌측 메뉴 → 시스템 → 모듈 관리 로 이동합니다.</li><li>설치된 모듈 목록에서 <strong>링크 프리뷰(preview)</strong> 를 찾아 우측의 <strong>제거</strong> 버튼을 누릅니다.</li><li>제거 후 oembed 가 preview 의 외부 액션(<code>dispPreviewCard</code> 등) 을 자동으로 흡수해 응답하므로, 외부 캐시·이메일·외부 사이트의 링크는 그대로 동작합니다.</li><li>본문에 저장된 <code>preview_card_*</code> 마크업 역시 oembed 가 동일한 클래스명으로 출력해 호환을 유지합니다(<em>호환 모드</em> ON 시).</li></ol>';
