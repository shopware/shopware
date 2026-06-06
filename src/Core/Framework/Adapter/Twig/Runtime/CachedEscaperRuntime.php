<?php

declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Twig\Runtime;

use Shopware\Core\Framework\Log\Package;
use Twig\Error\RuntimeError;
use Twig\Extension\RuntimeExtensionInterface;
use Twig\Markup;
use Twig\Runtime\EscaperRuntime;

/**
 * @internal
 */
#[Package('framework')]
final class CachedEscaperRuntime implements RuntimeExtensionInterface
{
    /**
     * Cache for escaped strings to avoid repeated escaping of the same content.
     * Reset between requests via {@see CachedEscaperRuntimeResetter} for long runner compatibility.
     *
     * @var array<string, mixed>
     */
    private static array $escapeCache = [];

    private readonly EscaperRuntime $innerRuntime;

    public function __construct(string $charset = 'UTF-8')
    {
        $this->innerRuntime = new EscaperRuntime($charset);
    }

    /**
     * Wraps Twig's {@see EscaperRuntime} to cache the escaped value to increase the performance.
     * Caching other types than `string` brings no value, as the checks for those types cost more than the cache brings benefit.
     * E.g. integers and floats are rarely occurring with the same value more than once.
     * Or e.g. {@see Markup} is directly returned anyway by Twig's internal escaper, due to `$autoescape` set to true for the internal usage in Twig, so also not worth caching.
     * Changing the logic here should be proven with performance measuering tools like Blackfire.
     *
     * @throws RuntimeError
     */
    public function escape(
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

        $result = $this->innerRuntime->escape($string, $strategy, $charset, $autoescape);

        if ($cacheKey === null) {
            return $result;
        }

        self::$escapeCache[$cacheKey] = $result;

        return $result;
    }

    /**
     * @param callable(string $string, string $charset): string $callable
     */
    public function setEscaper(string $strategy, callable $callable): void
    {
        $this->innerRuntime->setEscaper($strategy, $callable);
    }

    /**
     * @return array<string, callable(string $string, string $charset): string>
     */
    public function getEscapers(): array
    {
        return $this->innerRuntime->getEscapers();
    }

    /**
     * @param array<class-string<\Stringable>, string[]> $safeClasses
     */
    public function setSafeClasses(array $safeClasses = []): void
    {
        $this->innerRuntime->setSafeClasses($safeClasses);
    }

    /**
     * @param class-string<\Stringable> $class
     * @param string[] $strategies
     */
    public function addSafeClass(string $class, array $strategies): void
    {
        $this->innerRuntime->addSafeClass($class, $strategies);
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
