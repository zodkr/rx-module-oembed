/**
 * oembed - CKEditor 4 paste hook.
 *
 * 사용자가 URL 을 붙여넣으면 procOembedFetch 를 호출해 임베드/카드로
 * 변환된 wrapped_html 을 받아 CKEditor 의 dom API 로 노드를 교체한다.
 * 실패한 호스트는 sessionStorage 에 기록해 동일 호스트 재시도를 차단한다.
 *
 * 임베드 HTML 은 서버에서 화이트리스트 기반(htmlspecialchars + 정규화된
 * provider 출력)으로 생성하므로, CKEditor 의 createFromHtml/replace 를
 * 통해 자체 sanitizer 를 거쳐 삽입한다. innerHTML 을 직접 다루지 않는다.
 */
(function (window) {
  'use strict';

  if (!window.CKEDITOR) {
    return;
  }

  var URL_PATTERN = /(?:https?:)?\/\/[^\s<>"]+/i;
  var FAILED_HOSTS_KEY = 'oembed:failed_hosts';
  var FAILED_HOST_TTL_MS = 60 * 60 * 1000; // 1 시간
  var DEBUG = !!(window.localStorage && window.localStorage.getItem('oembed:debug'));

  function debug() {
    if (!DEBUG || !window.console) {
      return;
    }
    var args = ['[oembed]'].concat(Array.prototype.slice.call(arguments));
    (window.console.debug || window.console.log).apply(window.console, args);
  }

  function loadFailedHosts() {
    try {
      var raw = window.sessionStorage.getItem(FAILED_HOSTS_KEY);
      var failed = raw ? JSON.parse(raw) : {};
      // FAILED_HOST_TTL_MS 가 지난 항목은 만료 처리해 자동 재시도 가능하게 한다.
      // 일시적인 네트워크 실패나 CSRF 토큰 미세팅 같은 환경 문제로 한 번
      // 등록되면 영구 차단되는 부작용을 막는다.
      var now = Date.now();
      var changed = false;
      for (var host in failed) {
        if (Object.prototype.hasOwnProperty.call(failed, host)) {
          if (typeof failed[host] !== 'number' || (now - failed[host]) > FAILED_HOST_TTL_MS) {
            delete failed[host];
            changed = true;
          }
        }
      }
      if (changed) {
        window.sessionStorage.setItem(FAILED_HOSTS_KEY, JSON.stringify(failed));
      }
      return failed;
    } catch (e) {
      return {};
    }
  }

  function rememberFailedHost(host) {
    try {
      var failed = loadFailedHosts();
      failed[host] = Date.now();
      window.sessionStorage.setItem(FAILED_HOSTS_KEY, JSON.stringify(failed));
    } catch (e) {
      /* sessionStorage 가 차단된 환경 */
    }
  }

  function hostOf(url) {
    try {
      return new URL(url, window.location.href).host;
    } catch (e) {
      return '';
    }
  }

  function escapeHtml(value) {
    return String(value)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  function csrfToken() {
    // Rhymix 는 common.js 에서 Rhymix.getCSRFToken() 으로 토큰을 노출한다.
    // 일부 환경 (admin 화면 일부) 은 window.csrf_token 으로도 들어 있어 폴백.
    if (window.Rhymix && typeof window.Rhymix.getCSRFToken === 'function') {
      return (window.Rhymix.getCSRFToken() || '').toString();
    }
    return (window.csrf_token || '').toString();
  }

  function fetchOembed(url) {
    var body = new window.URLSearchParams();
    body.append('url', url);
    body.append('mid', window.current_mid || '');
    var token = csrfToken();
    if (token) {
      body.append('_rx_csrf_token', token);
    }
    return window.fetch('/index.php?module=oembed&act=procOembedFetch', {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-Token': token,
        'Accept': 'application/json'
      },
      body: body.toString()
    }).then(function (res) {
      return res.ok ? res.json() : { kind: 'fail' };
    }).catch(function () {
      return { kind: 'fail' };
    });
  }

  function pickPastedUrl(data) {
    if (!data) {
      return null;
    }
    // CKEditor 4 는 paste 시 dataValue 를 <p>...</p> 등으로 감싸 넣고 끝에 개행을
    // 남기는 경우가 많다. 태그 + 공백류 제거 후 단일 URL 만 남았는지 비교한다.
    // URL_PATTERN 자체가 \s 를 배제하므로, 비교 대상도 공백 제거된 형태.
    var text = (data.dataValue || '').replace(/<[^>]+>/g, '').replace(/\s+/g, '');
    if (!text) {
      return null;
    }
    var match = text.match(URL_PATTERN);
    if (!match || match[0] !== text) {
      debug('skip non-url paste:', text);
      return null;
    }
    return match[0];
  }

  function placeholderHtml(url) {
    var safe = escapeHtml(url);
    return '<p data-oembed-pending="1"><a href="' + safe + '">' + safe + '</a></p>';
  }

  function findPlaceholder(editor, url) {
    var doc = editor.document;
    if (!doc) {
      return null;
    }
    var nodeList = doc.find('p[data-oembed-pending="1"]');
    var count = nodeList.count();
    for (var i = 0; i < count; i++) {
      var node = nodeList.getItem(i);
      if ((node.getText() || '').trim() === url) {
        return node;
      }
    }
    return null;
  }

  function replacePlaceholder(editor, url, html) {
    var node = findPlaceholder(editor, url);
    if (!node) {
      return;
    }
    var newNode = window.CKEDITOR.dom.element.createFromHtml(html, editor.document);
    newNode.replace(node);
  }

  function removePlaceholder(editor, url) {
    var node = findPlaceholder(editor, url);
    if (node) {
      node.removeAttribute('data-oembed-pending');
    }
  }

  function handlePaste(editor, evt) {
    var url = pickPastedUrl(evt.data);
    if (!url) {
      return;
    }
    var host = hostOf(url);
    if (host && loadFailedHosts()[host]) {
      debug('skip blacklisted host:', host);
      return;
    }
    debug('paste url:', url);

    evt.data.dataValue = placeholderHtml(url);

    fetchOembed(url).then(function (resp) {
      if ((resp.kind === 'embed' || resp.kind === 'card') && resp.wrapped_html) {
        debug('replace placeholder:', resp.kind, url);
        replacePlaceholder(editor, url, resp.wrapped_html);
      } else {
        debug('fetch failed:', url, resp);
        if (host) {
          rememberFailedHost(host);
        }
        removePlaceholder(editor, url);
      }
    });
  }

  function attach(editor) {
    if (editor._oembedAttached) {
      return;
    }
    editor._oembedAttached = true;
    editor.on('paste', function (e) {
      handlePaste(editor, e);
    });
  }

  // 새로 생성되는 인스턴스
  window.CKEDITOR.on('instanceReady', function (ev) {
    attach(ev.editor);
  });

  // _ckeditor.js 가 인스턴스 생성 후에 로드되는 경우 (Rhymix XeCkEditor 가 직접
  // 인스턴스를 만든 뒤 자산을 잇따라 로드하는 시나리오) 를 위한 백업.
  if (window.CKEDITOR.instances) {
    for (var name in window.CKEDITOR.instances) {
      if (Object.prototype.hasOwnProperty.call(window.CKEDITOR.instances, name)) {
        attach(window.CKEDITOR.instances[name]);
      }
    }
  }
})(window);
