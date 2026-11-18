<?php

$lang->cmd_oembed = 'oEmbed';
$lang->cmd_oembed_general_config = '기본 설정';
$lang->cmd_oembed_providers = 'Provider 관리';
$lang->cmd_oembed_cleanup = '이전 버전 본문 정리';

$lang->oembed_skin = '카드 스킨';
$lang->oembed_skin_desc = '미리보기 카드의 디자인을 결정합니다. 새 스킨은 modules/oembed/skins/ 폴더에 추가하면 자동으로 인식됩니다. 스킨 변경은 새로 작성하는 카드부터 적용되며, 이미 작성된 카드의 모양은 바뀌지 않습니다.';

$lang->oembed_compatible_mode = 'preview 모듈 호환';
$lang->oembed_compatible_mode_desc = 'preview 모듈로 작성해 두었던 기존 게시물의 카드와 임베드를 그대로 표시합니다. 본문에 저장된 <code>preview_card_*</code>·<code>media_embed_wrapper</code> 마크업이 같은 모양으로 보이도록 게시물 읽기 화면에서 호환 스타일과 후처리 스크립트(인스타그램·페이스북·X SDK 자동 로드, lazy iframe 활성화) 를 함께 적용합니다. preview 모듈을 사용한 적 없는 새 사이트라면 꺼두셔도 됩니다.';

$lang->oembed_legacy_wrappers = '이전 버전 게시물 호환';
$lang->oembed_legacy_wrappers_desc = 'oEmbed 1.0.4 이하에서 작성되어 본문에 <code>editor_component="oembed"</code> 속성이 남아 있는 게시물·댓글의 임베드와 카드를 표시합니다. 최신 Rhymix 는 이 속성이 붙은 태그를 출력 시 비우기 때문에, 켜 두면 화면에 그리기 직전에 속성을 떼어 냅니다 (DB 의 본문은 수정하지 않습니다). 이전 버전에서 업데이트한 사이트는 자동으로 켜지고, 새로 설치한 사이트는 꺼진 상태로 시작합니다.';

$lang->oembed_cleanup_intro = 'oEmbed 1.0.4 이하에서 작성된 게시물·댓글의 본문에 남아 있는 <code>editor_component="oembed"</code> 속성을 DB 에서 영구히 제거합니다. 정리를 마치면 <em>이전 버전 게시물 호환</em> 옵션 없이도 해당 글의 임베드와 카드가 표시됩니다.';
$lang->oembed_cleanup_warning_title = '실행하기 전에 반드시 DB 를 백업하세요';
$lang->oembed_cleanup_warning_body = '<ul><li>게시물(<code>documents</code>)·댓글(<code>comments</code>) 테이블의 본문을 직접 수정하며, <strong>실행한 뒤에는 되돌릴 수 없습니다.</strong></li><li>게시물과 댓글이 많으면 <strong>작업이 오래 걸릴 수 있습니다.</strong> 진행 중에는 이 창을 닫거나 다른 페이지로 이동하지 마세요.</li><li>작업 도중 오류·중단·서버 문제 등으로 <strong>본문이 손상되거나 소실될 수 있습니다.</strong> 그런 경우 백업으로만 복구할 수 있으므로, 백업을 마친 뒤에 진행하시기를 강력히 권장합니다.</li><li>가능하면 방문자가 적은 시간대에 실행하세요.</li></ul>';
$lang->oembed_cleanup_confirm = 'DB 백업을 마쳤고, 위 내용을 확인했습니다.';
$lang->oembed_cleanup_start = '정리 시작';
$lang->oembed_cleanup_resume = '이어서 진행';
$lang->oembed_cleanup_stop = '중지';
$lang->oembed_cleanup_target_documents = '게시물';
$lang->oembed_cleanup_target_comments = '댓글';
$lang->oembed_cleanup_col_target = '대상';
$lang->oembed_cleanup_col_total = '전체';
$lang->oembed_cleanup_col_scanned = '검사';
$lang->oembed_cleanup_col_updated = '수정';
$lang->oembed_cleanup_col_skipped = '보류';
$lang->oembed_cleanup_skipped_help = '보류는 정리 대상이지만 안전 검사를 통과하지 못했거나 작업 도중 내용이 바뀌어 건드리지 않은 항목입니다. 다시 실행하면 재시도합니다.';
$lang->oembed_cleanup_status_preparing = '대상 수를 세는 중입니다…';
$lang->oembed_cleanup_status_running = '정리 중입니다. 이 창을 닫지 마세요.';
$lang->oembed_cleanup_status_stopping = '진행 중인 묶음을 마치는 대로 멈춥니다…';
$lang->oembed_cleanup_status_stopped = '중지했습니다. 이어서 진행을 누르면 멈춘 지점부터 계속합니다.';
$lang->oembed_cleanup_status_done = '정리를 마쳤습니다. 보류된 항목이 없다면 기본 설정의 이전 버전 게시물 호환을 꺼도 됩니다.';
$lang->oembed_cleanup_status_error = '오류가 발생해 멈췄습니다. 이어서 진행을 누르면 멈춘 지점부터 다시 시도합니다.';
$lang->msg_oembed_cleanup_confirm_required = 'DB 백업 확인에 체크해야 실행할 수 있습니다.';

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
