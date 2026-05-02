<?php

namespace Rhymix\Modules\Oembed\Models;

abstract class Provider
{
  public const TYPE_MULTIMEDIA = 'multimedia';
  public const TYPE_SOCIAL = 'social';

  public string $name = '';
  public string $type = self::TYPE_MULTIMEDIA;
  public bool $oembed = false;
  public array $hosts = [];

  /**
   * Pattern map.
   *
   * key   : PCRE pattern (e.g. '#youtube\.com/watch\?v=([\w-]+)#i')
   * value : list of capture-group names whose order matches the pattern.
   *
   * Example: ['#youtube\.com/watch\?v=([\w-]+)#i' => ['video_id']]
   */
  public array $patterns = [];

  /**
   * Build the final embed HTML for matched data.
   *
   * @param array{pattern: string, captures: array<string,string>, url: string} $matchData
   */
  abstract public function buildEmbed(array $matchData, ?int $width = null, ?int $height = null): string;

  /**
   * Try to match the given URL against this provider's pattern map.
   * Returns null on miss, otherwise a struct describing the match.
   *
   * @return array{pattern: string, captures: array<string,string>, url: string}|null
   */
  public function match(string $url): ?array
  {
    foreach ($this->patterns as $pattern => $captureNames) {
      if (preg_match($pattern, $url, $m)) {
        $captures = [];
        foreach ($captureNames as $i => $key) {
          $captures[$key] = $m[$i + 1] ?? '';
        }
        return ['pattern' => $pattern, 'captures' => $captures, 'url' => $url];
      }
    }
    return null;
  }

  /**
   * Resolve final iframe dimensions.
   * If both axes are missing, fall back to type-aware defaults (multimedia=16:9, otherwise 4:3).
   *
   * @return array{0:int,1:int} [width, height]
   */
  public function getDimensions(?int $width, ?int $height): array
  {
    if ($width && $height) {
      return [$width, $height];
    }
    [$rw, $rh] = $this->type === self::TYPE_MULTIMEDIA ? [16, 9] : [4, 3];
    if ($width && !$height) {
      return [$width, (int) round($width * $rh / $rw)];
    }
    if ($height && !$width) {
      return [(int) round($height * $rw / $rh), $height];
    }
    $defaultWidth = 640;
    return [$defaultWidth, (int) round($defaultWidth * $rh / $rw)];
  }
}
