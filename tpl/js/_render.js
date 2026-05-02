/**
 * oembed - render-time post-processor.
 *
 * 글 보기 페이지에서 본문에 저장된 레거시 마크업(preview 시절)과 새로
 * 생성된 임베드 마크업을 안전하게 활성화한다.
 *
 *   - <iframe data-src="..."> 를 src 로 채워 lazy iframe 을 활성화한다.
 *   - .instagram-media / .fb-post / .fb-video / .imgur-embed-pub 가 본문에
 *     있으면 각 서비스의 공식 SDK 를 한 번씩만 동적으로 로드한다.
 *
 * 호환 모드 ON 일 때만 EventHandlers 가 이 스크립트를 주입한다.
 */
(function (window, document) {
  'use strict';

  var loadedSdk = new Set();

  function loadSdk(src) {
    if (loadedSdk.has(src)) {
      return;
    }
    if (document.querySelector('script[src="' + src + '"]')) {
      loadedSdk.add(src);
      return;
    }
    var script = document.createElement('script');
    script.async = true;
    script.defer = true;
    script.src = src;
    document.body.appendChild(script);
    loadedSdk.add(src);
  }

  function activate() {
    var lazyIframes = document.querySelectorAll('iframe[data-src]');
    for (var i = 0; i < lazyIframes.length; i++) {
      var iframe = lazyIframes[i];
      var dataSrc = iframe.getAttribute('data-src');
      if (dataSrc && !iframe.getAttribute('src')) {
        iframe.setAttribute('src', dataSrc);
      }
    }

    if (document.querySelector('.instagram-media')) {
      loadSdk('https://www.instagram.com/embed.js');
    }
    if (document.querySelector('.fb-post, .fb-video')) {
      loadSdk('https://connect.facebook.net/ko_KR/sdk.js#xfbml=1&version=v18.0');
    }
    if (document.querySelector('.imgur-embed-pub')) {
      loadSdk('https://s.imgur.com/min/embed.js');
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', activate);
  } else {
    activate();
  }
})(window, document);
