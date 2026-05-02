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

  function loadFailedHosts() {
    try {
      var raw = window.sessionStorage.getItem(FAILED_HOSTS_KEY);
      return raw ? JSON.parse(raw) : {};
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
    return (window.rx_csrf_token || window.csrf_token || '').toString();
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
    var text = (data.dataValue || '').replace(/<[^>]+>/g, '').trim();
    if (!text) {
      return null;
    }
    var match = text.match(URL_PATTERN);
    if (!match || match[0].length !== text.length) {
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
      return;
    }

    evt.data.dataValue = placeholderHtml(url);

    fetchOembed(url).then(function (resp) {
      if (resp.kind === 'embed' && resp.wrapped_html) {
        replacePlaceholder(editor, url, resp.wrapped_html);
      } else {
        if (host) {
          rememberFailedHost(host);
        }
        removePlaceholder(editor, url);
      }
    });
  }

  window.CKEDITOR.on('instanceReady', function (ev) {
    var editor = ev.editor;
    editor.on('paste', function (e) {
      handlePaste(editor, e);
    });
  });
})(window);
