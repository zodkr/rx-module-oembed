/**
 * oEmbed 관리자 — 이전 버전 본문 정리 화면 (views/admin/cleanup.blade.php).
 *
 * 서버의 procOembedAdminCleanupLegacy 는 한 번에 약 2초 분량만 처리하고
 * cursor 를 돌려준다. 이 스크립트가 그 cursor 를 넘기며 끝날 때까지 이어
 * 부른다. 긴 작업을 한 요청에 몰아넣지 않으므로 웹 서버 제한 시간에 걸리지
 * 않고, 중지했다가 같은 지점에서 다시 시작할 수 있다.
 *
 * 문구는 템플릿이 #oembedCleanup 의 data-* 속성으로 넘겨준다.
 */
(function ($) {
  'use strict';

  $(function () {
    var $root = $('#oembedCleanup');
    if (!$root.length) {
      return;
    }

    var targets = String($root.data('targets') || '').split(',').filter(Boolean);
    var $confirm = $('#oembedCleanupConfirm');
    var $start = $('#oembedCleanupStart');
    var $stop = $('#oembedCleanupStop');
    var $panel = $root.find('.oembed-cleanup-progress');
    var $bar = $root.find('.oembed-cleanup-bar');
    var $status = $root.find('.oembed-cleanup-status');
    var $error = $root.find('.oembed-cleanup-error');

    var state = {
      running: false,
      stopRequested: false,
      initialized: false,
      finished: false,
      index: 0,    // 처리 중인 targets 의 위치
      cursor: 0,   // 그 대상에서 마지막으로 처리한 PK
      totals: {},
      stats: {}
    };
    targets.forEach(function (target) {
      state.totals[target] = 0;
      state.stats[target] = { scanned: 0, updated: 0, skipped: 0 };
    });

    function text(key) {
      return String($root.data(key) || '');
    }

    function format(value) {
      return Number(value || 0).toLocaleString();
    }

    function render() {
      var total = 0;
      var scanned = 0;
      targets.forEach(function (target) {
        var $row = $root.find('tr[data-target="' + target + '"]');
        var stats = state.stats[target];
        $row.find('[data-cell="total"]').text(state.initialized ? format(state.totals[target]) : '-');
        $row.find('[data-cell="scanned"]').text(format(stats.scanned));
        $row.find('[data-cell="updated"]').text(format(stats.updated));
        $row.find('[data-cell="skipped"]').text(format(stats.skipped));
        total += state.totals[target];
        scanned += stats.scanned;
      });

      // 작업 중에 글이 늘면 검사 수가 처음 센 전체 수를 넘을 수 있다.
      var percent = state.finished ? 100 : (total > 0 ? Math.min(100, Math.floor(scanned / total * 100)) : 0);
      $bar.attr('aria-valuenow', percent).children('span').css('width', percent + '%');

      $start.prop('disabled', state.running || state.finished || !$confirm.prop('checked'));
      $start.text(state.initialized ? text('labelResume') : text('labelStart'));
      $stop.prop('hidden', !state.running).prop('disabled', state.stopRequested);
      $confirm.prop('disabled', state.running || state.finished);
    }

    function setStatus(key) {
      $status.text(text(key));
    }

    function halt(statusKey, errorMessage) {
      state.running = false;
      state.stopRequested = false;
      setStatus(statusKey);
      $error.prop('hidden', !errorMessage).text(errorMessage || '');
      render();
    }

    function request(params, onSuccess) {
      params.confirm_backup = 'Y';
      window.exec_json('oembed.procOembedAdminCleanupLegacy', params, onSuccess, function (data) {
        halt('msgError', data && data.message ? String(data.message) : '');
        return false; // exec_json 의 기본 alert 대신 화면에 표시한다.
      });
    }

    function step() {
      if (state.stopRequested) {
        halt('msgStopped');
        return;
      }
      if (state.index >= targets.length) {
        state.finished = true;
        halt('msgDone');
        return;
      }

      var target = targets[state.index];
      request({ step: 'run', target: target, cursor: state.cursor }, function (data) {
        var stats = state.stats[target];
        stats.scanned += parseInt(data.scanned, 10) || 0;
        stats.updated += parseInt(data.updated, 10) || 0;
        stats.skipped += parseInt(data.skipped, 10) || 0;
        state.cursor = parseInt(data.cursor, 10) || 0;
        if (data.done) {
          state.index += 1;
          state.cursor = 0;
        }
        render();
        step();
      });
    }

    function start() {
      if (state.running || state.finished || !$confirm.prop('checked')) {
        return;
      }
      state.running = true;
      state.stopRequested = false;
      $panel.prop('hidden', false);
      $error.prop('hidden', true).text('');

      if (state.initialized) {
        setStatus('msgRunning');
        render();
        step();
        return;
      }

      setStatus('msgPreparing');
      render();
      request({ step: 'init' }, function (data) {
        var totals = data.totals || {};
        targets.forEach(function (target) {
          state.totals[target] = parseInt(totals[target], 10) || 0;
        });
        state.initialized = true;
        setStatus('msgRunning');
        render();
        step();
      });
    }

    $confirm.on('change', render);
    $start.on('click', start);
    $stop.on('click', function () {
      if (state.running) {
        state.stopRequested = true;
        setStatus('msgStopping');
        render();
      }
    });

    // 진행 중에 실수로 창을 닫거나 이동하지 않도록 브라우저 확인창을 띄운다.
    $(window).on('beforeunload', function (event) {
      if (state.running) {
        event.preventDefault();
        event.originalEvent.returnValue = '';
        return '';
      }
    });

    render();
  });
})(jQuery);
