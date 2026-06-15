<?php

declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Twig\Runtime;

use Shopware\Core\Framework\Log\Package;
use Twig\Error\RuntimeError;
use Twig\Markup;
use Twig\Runtime\EscaperRuntime;

/**
 * @internal
 */
#[Package('framework')]
final class CachedEscaperRuntime
{
    /**
     * Cache for escaped strings to avoid repeated escaping of the same content.
     * Reset between requests via {@see CachedEscaperRuntimeResetter} for long runner compatibility.
     *
     * @var array<string, string>
     */
    private static array $escapeCache = [];

    private function __construct()
    {
    }

    /**
     * Wraps the original Twig {@see EscaperRuntime} to cache the escaped value to increase the performance.
     * Caching other types than `string` brings no value, as the checks for those types cost more than the cache brings benefit.
     * E.g. integers and floats are rarely occurring with the same value more than once.
     * Or e.g. {@see Markup} is directly returned anyway by original escaper, due to `$autoescape` set to true for the internal usage in Twig, so also not worth caching.
     * Changing the logic here should be proven with performance measuering tools like Blackfire.
     *
     * @throws RuntimeError
     */
    public static function escape(
        EscaperRuntime $originalEscaperRuntime,
        mixed $string,
        string $strategy = 'html',
        ?string $charset = null,
        bool $autoescape = false
    ): mixed {
        $cacheKey = null;

        if (\is_string($string)) {
            $cacheKey = \sprintf('%s-%s-%s', $string, $strategy, $charset);
            if (isset(self::$escapeCache[$cacheKey])) {
                return self::$escapeCache[$cacheKey];
            }
        }

        $result = $originalEscaperRuntime->escape($string, $strategy, $charset, $autoescape);

        if ($cacheKey === null) {
            return $result;
        }

        self::$escapeCache[$cacheKey] = $result;

        return $result;
    }

    /**
     * Resets the escape filter cache.
     * This method is called by {@see CachedEscaperRuntimeResetter} between requests
     * in long runner environments (RoadRunner, FrankenPHP, Swoole) to prevent
     * memory leaks from unbounded cache growth.
     */
    public static function resetEscapeCache(): void
    {
        self::$escapeCache = [];
    }
}
