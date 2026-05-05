/**
 * oembed - render-time SDK loader (view pages only).
 *
 * 글 보기 페이지에서 본문 DOM 에 박제된 임베드 마크업을 검사하고,
 * 매칭되는 provider SDK 를 `document.head` 에 동적으로 주입한다.
 *
 * - 자산 맵은 EventHandlers 가 inline `<script>` 로 head 에 emit 한
 *   `window.oembedEmbedAssets` 에 들어 있다 ([{selector, script}, ...]).
 * - SDK <script> 를 본문에 함께 저장하면 HTMLPurifier 에 의해 글 저장이
 *   실패하므로, 임베드 markup 자체에는 SDK 가 없고 이 파일이 view 시점에
 *   채워 넣는 구조다.
 * - 호환 모드 (`window.oembedCompatibleMode === true`) 일 때는 추가로
 *   레거시 preview 모듈의 lazy iframe (<iframe data-src="...">) 도
 *   활성화한다.
 *
 * DOMContentLoaded 이후에 실행되므로 본문이 모듈/addon/sanitizer 모두를
 * 통과한 최종 형태로 DOM 에 들어가 있다 — 서버측 트리거 시점의 string
 * 스캔보다 안정적이다.
 */
(function (window, document) {
  'use strict';

  function loadSdk(asset, loaded) {
    var src = asset.script;
    if (loaded[src]) {
      return;
    }
    if (document.querySelector('script[data-oembed-sdk="' + src + '"]')) {
      loaded[src] = true;
      return;
    }
    var script = document.createElement('script');
    script.async = true;
    // crossOrigin 은 provider 가 명시한 경우에만 설정. CDN 이 CORS 헤더를
    // 보내지 않는 SDK (Imgur, Instagram 등) 에 anonymous 모드를 강제하면
    // 브라우저가 로딩을 차단한다.
    if (asset.crossorigin === true) {
      script.crossOrigin = 'anonymous';
    }
    script.setAttribute('data-oembed-sdk', src);
    script.src = src;
    (document.head || document.documentElement).appendChild(script);
    loaded[src] = true;
  }

  function activate() {
    var assets = Array.isArray(window.oembedEmbedAssets) ? window.oembedEmbedAssets : [];
    var loaded = {};
    for (var i = 0; i < assets.length; i++) {
      var asset = assets[i];
      if (!asset || typeof asset.selector !== 'string' || typeof asset.script !== 'string') {
        continue;
      }
      try {
        if (document.querySelector(asset.selector)) {
          loadSdk(asset, loaded);
        }
      } catch (e) {
        // 잘못된 selector 는 조용히 건너뛴다 — provider 추가 시 typo 가
        // 다른 SDK 로딩까지 막지 않도록 격리한다.
      }
    }

    if (window.oembedCompatibleMode === true) {
      var lazyIframes = document.querySelectorAll('iframe[data-src]');
      for (var j = 0; j < lazyIframes.length; j++) {
        var iframe = lazyIframes[j];
        var dataSrc = iframe.getAttribute('data-src');
        if (dataSrc && !iframe.getAttribute('src')) {
          iframe.setAttribute('src', dataSrc);
        }
      }
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', activate);
  } else {
    activate();
  }
})(window, document);
