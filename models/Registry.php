<?php

namespace Rhymix\Modules\Oembed\Models;

use Rhymix\Framework\Cache;

class Registry
{
  private const PROVIDERS_DIR = __DIR__ . '/../providers';
  private const CACHE_KEY = 'oembed:registry:providers';
  private const CACHE_TTL = 3600;

  /** @var array<string, Provider>|null */
  private static ?array $providers = null;

  /**
   * Try to match the given URL against any registered (and enabled) provider.
   * Returns the matching provider plus its match data, or null on miss.
   *
   * @return array{provider: Provider, match: array{pattern: string, captures: array<string,string>, url: string}}|null
   */
  public static function match(string $url): ?array
  {
    foreach (self::getProviders() as $provider) {
      $matchData = $provider->match($url);
      if ($matchData !== null) {
        return ['provider' => $provider, 'match' => $matchData];
      }
    }
    return null;
  }

  /**
   * Return every registered provider keyed by class basename (e.g. 'Youtube').
   * Disabled providers (per module config) are skipped.
   *
   * @return array<string, Provider>
   */
  public static function getProviders(): array
  {
    if (self::$providers === null) {
      self::$providers = self::loadProviders();
    }
    return self::filterEnabled(self::$providers);
  }

  /**
   * Force re-scan of the providers directory.
   */
  public static function flush(): void
  {
    self::$providers = null;
    Cache::delete(self::CACHE_KEY);
  }

  /**
   * @return array<string, Provider>
   */
  private static function loadProviders(): array
  {
    $cacheKey = self::CACHE_KEY . ':' . self::computeMtime();
    $cached = Cache::get($cacheKey);
    $classes = is_array($cached) ? $cached : self::scanProviderClasses();
    if (!is_array($cached)) {
      Cache::set($cacheKey, $classes, self::CACHE_TTL);
    }

    $providers = [];
    foreach ($classes as $class) {
      if (class_exists($class)) {
        $instance = new $class();
        if ($instance instanceof Provider) {
          $providers[self::shortName($class)] = $instance;
        }
      }
    }
    return $providers;
  }

  /**
   * @return list<class-string>
   */
  private static function scanProviderClasses(): array
  {
    $files = glob(self::PROVIDERS_DIR . '/*.php') ?: [];
    $classes = [];
    foreach ($files as $file) {
      $name = basename($file, '.php');
      $classes[] = 'Rhymix\\Modules\\Oembed\\Providers\\' . $name;
    }
    return $classes;
  }

  /**
   * Aggregate mtime so the cache invalidates when a provider file changes.
   */
  private static function computeMtime(): int
  {
    $files = glob(self::PROVIDERS_DIR . '/*.php') ?: [];
    $mtime = filemtime(self::PROVIDERS_DIR) ?: 0;
    foreach ($files as $file) {
      $mtime = max($mtime, filemtime($file) ?: 0);
    }
    return $mtime;
  }

  /**
   * @param array<string, Provider> $providers
   * @return array<string, Provider>
   */
  private static function filterEnabled(array $providers): array
  {
    $config = Config::getConfig();
    $disabled = is_array($config->disabled_providers ?? null) ? $config->disabled_providers : [];
    if (!$disabled) {
      return $providers;
    }
    return array_filter($providers, static fn(string $key) => !in_array($key, $disabled, true), ARRAY_FILTER_USE_KEY);
  }

  private static function shortName(string $fqcn): string
  {
    $pos = strrpos($fqcn, '\\');
    return $pos === false ? $fqcn : substr($fqcn, $pos + 1);
  }
}
