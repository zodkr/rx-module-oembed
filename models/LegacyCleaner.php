<?php

namespace Rhymix\Modules\Oembed\Models;

use Rhymix\Framework\DB;

/**
 * 게시물 / 댓글 본문에 남아 있는 legacy wrapper (`editor_component="oembed"`)
 * 를 DB 에서 영구히 정리한다. 어드민 → oEmbed → 이전 버전 본문 정리 화면
 * (Admin::procOembedAdminCleanupLegacy) 이 여러 번의 짧은 요청으로 나눠 부른다.
 *
 * 변환 자체는 렌더 시점 정규화와 같은 LegacyWrapper::normalize() 를 쓴다 —
 * 정리 후 DB 에 남는 본문은 지금 화면에 나가고 있는 것과 같은 마크업이다.
 *
 * 한 번에 `UPDATE … SET content = REPLACE(…)` 를 돌리지 않는 이유:
 *   - 인덱스 없는 LONGTEXT 를 전체 스캔하는 단일 UPDATE 는 큰 사이트에서
 *     수십 초~수 분이 걸려 웹 요청 제한 시간을 넘기고, 스캔한 행을 잠근다.
 *   - 문자열 치환은 속성 하나만 지우므로 LegacyWrapper 의 allowlist 재조립
 *     (보안 정리) 을 거치지 않는다.
 * 대신 PK 순서로 작은 구간씩 읽고, 대상 행만 PK 로 하나씩 고친다. 각 UPDATE
 * 는 독립적이라 중간에 멈춰도 그때까지 고친 행은 유효하고, 다시 실행하면
 * 남은 행만 처리한다 (이미 정리된 행은 LIKE 에 걸리지 않는다).
 */
class LegacyCleaner
{
  /** 정리 대상 테이블과 PK. 값은 SQL 에 그대로 들어가므로 상수로만 둔다. */
  private const TARGETS = [
    'documents' => ['table' => 'documents', 'key' => 'document_srl'],
    'comments' => ['table' => 'comments', 'key' => 'comment_srl'],
  ];

  /** 한 구간에서 훑는 행 수. 구간마다 시간 예산을 다시 확인한다. */
  private const WINDOW_ROWS = 1000;

  /**
   * 후보 행을 고르는 LIKE 패턴. `_` 가 와일드카드라 실제보다 넓게 잡히지만
   * 최종 판정은 LegacyWrapper::normalize() 가 한다 (바뀌는 게 없으면 건너뜀).
   */
  private const LIKE_PATTERN = '%editor_component="oembed"%';

  /**
   * @return string[]
   */
  public static function getTargets(): array
  {
    return array_keys(self::TARGETS);
  }

  public static function isTarget(string $target): bool
  {
    return isset(self::TARGETS[$target]);
  }

  /**
   * 진행률 표시용 전체 행 수.
   */
  public static function countRows(string $target): int
  {
    $spec = self::TARGETS[$target];
    $stmt = self::query(sprintf('SELECT COUNT(*) FROM %s', $spec['table']));
    return (int) $stmt->fetchColumn();
  }

  /**
   * $cursor (마지막으로 처리한 PK) 다음부터 시간 예산이 허락하는 만큼
   * 구간을 처리하고, 이어서 부를 때 넘길 cursor 와 집계를 돌려준다.
   *
   * @return array{cursor:int, scanned:int, updated:int, skipped:int, done:bool}
   *   scanned — 훑은 행 수 (대상 아닌 행 포함)
   *   updated — 본문을 고친 행 수
   *   skipped — 고쳐야 하지만 건드리지 않은 행 수 (isSafeRewrite 실패, 또는
   *             읽은 뒤 내용이 바뀜). 다시 실행하면 재시도된다.
   */
  public static function run(string $target, int $cursor, float $timeBudget = 2.0): array
  {
    $spec = self::TARGETS[$target];
    $table = $spec['table'];
    $key = $spec['key'];
    $deadline = microtime(true) + $timeBudget;
    $result = ['cursor' => max(0, $cursor), 'scanned' => 0, 'updated' => 0, 'skipped' => 0, 'done' => false];

    do {
      // 다음 WINDOW_ROWS 행의 PK 상한을 먼저 구한다 (PK 인덱스만 읽는다).
      // srl 은 모든 테이블이 공유하는 시퀀스라 값 범위로 자르면 구간마다
      // 행 수가 들쭉날쭉해져서, 행 수 기준으로 자른다.
      $window = self::query(
        sprintf(
          'SELECT MAX(w.srl) AS upper_srl, COUNT(*) AS row_count FROM (SELECT %1$s AS srl FROM %2$s WHERE %1$s > ? ORDER BY %1$s LIMIT %3$d) w',
          $key,
          $table,
          self::WINDOW_ROWS
        ),
        $result['cursor']
      )->fetch(\PDO::FETCH_OBJ);
      $rowCount = (int) ($window->row_count ?? 0);
      if ($rowCount === 0) {
        $result['done'] = true;
        break;
      }
      $upper = (int) $window->upper_srl;

      $rows = self::query(
        sprintf('SELECT %1$s AS srl, content FROM %2$s WHERE %1$s > ? AND %1$s <= ? AND content LIKE ?', $key, $table),
        $result['cursor'],
        $upper,
        self::LIKE_PATTERN
      )->fetchAll(\PDO::FETCH_OBJ);

      foreach ($rows as $row) {
        $old = (string) $row->content;
        $new = LegacyWrapper::normalize($old);
        if ($new === $old) {
          // LIKE 에는 걸렸지만 정리할 wrapper 가 아니다 (본문 텍스트로 언급한 글 등).
          continue;
        }
        if (self::isSafeRewrite($old, $new) && self::updateContent($table, $key, (int) $row->srl, $old, $new)) {
          $result['updated']++;
          if ($target === 'documents') {
            \DocumentController::clearDocumentCache((int) $row->srl);
          }
        } else {
          $result['skipped']++;
        }
      }

      $result['cursor'] = $upper;
      $result['scanned'] += $rowCount;
      if ($rowCount < self::WINDOW_ROWS) {
        $result['done'] = true;
        break;
      }
    } while (microtime(true) < $deadline);

    return $result;
  }

  /**
   * 변환이 태그 속성만 건드렸는지 확인한다. 정규화는 wrapper 의 여는 태그만
   * 다시 쓰므로 태그를 걷어낸 텍스트는 전후가 같아야 한다. 다르면 예상하지
   * 못한 입력이라는 뜻이니 DB 에는 쓰지 않는다 (그 행은 렌더 시점 정규화가
   * 계속 맡는다).
   */
  private static function isSafeRewrite(string $old, string $new): bool
  {
    return $new !== '' && strip_tags($new) === strip_tags($old);
  }

  /**
   * 읽었던 본문과 DB 의 현재 본문이 바이트 단위로 같을 때만 쓴다.
   *
   * `MD5(content) = ?` 조건이 두 가지를 막는다.
   *   - 읽은 뒤 사용자가 그 글을 수정한 경우 — 덮어쓰면 수정분이 사라진다.
   *   - 읽어 온 값이 저장된 값과 다른 경우 (연결 문자셋과 컬럼 문자셋이
   *     어긋나 일부 문자가 변환돼 온 경우 등) — 그 값을 되쓰면 본문이 깨진다.
   * 어느 쪽이든 0 행이 갱신되고 호출부가 skipped 로 센다.
   */
  private static function updateContent(string $table, string $key, int $srl, string $old, string $new): bool
  {
    $stmt = self::query(
      sprintf('UPDATE %s SET content = ? WHERE %s = ? AND MD5(content) = ?', $table, $key),
      $new,
      $srl,
      md5($old)
    );
    return $stmt->rowCount() === 1;
  }

  /**
   * 테이블 접두사는 DB::query() 가 붙인다.
   */
  private static function query(string $sql, ...$args): \PDOStatement
  {
    $stmt = DB::getInstance()->query($sql, ...$args);
    if (!$stmt) {
      throw new \RuntimeException('oembed legacy cleanup: database query failed');
    }
    return $stmt;
  }
}
