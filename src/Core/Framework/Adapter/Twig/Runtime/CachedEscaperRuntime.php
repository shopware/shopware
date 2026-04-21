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
class CachedEscaperRuntime implements RuntimeExtensionInterface
{
    /**
     * Cache for escaped strings to avoid repeated escaping of the same content.
     * Reset between requests via {@see CachedEscaperRuntimeResetter} for long runner compatibility.
     *
     * @var array<string, array<string, string|Markup|mixed>>
     */
    private static array $escapeCache = [];

    public function __construct(
        private readonly EscaperRuntime $originalEscaperRuntime,
    ) {
    }

    /**
     * Mimics the public API of {@see EscaperRuntime} as it is final and cannot be extended
     *
     * @codeCoverageIgnore
     */
    public function setEscaper(string $strategy, callable $callable): void
    {
        $this->originalEscaperRuntime->setEscaper($strategy, $callable);
    }

    /**
     * Mimics the public API of {@see EscaperRuntime} as it is final and cannot be extended
     *
     * @codeCoverageIgnore
     *
     * @return array<string, callable(string $string, string $charset): string>
     */
    public function getEscapers(): array
    {
        return $this->originalEscaperRuntime->getEscapers();
    }

    /**
     * Mimics the public API of {@see EscaperRuntime} as it is final and cannot be extended
     *
     * @codeCoverageIgnore
     *
     * @param array<class-string<\Stringable>, string[]> $safeClasses
     */
    public function setSafeClasses(array $safeClasses = []): void
    {
        $this->originalEscaperRuntime->setSafeClasses($safeClasses);
    }

    /**
     * Mimics the public API of {@see EscaperRuntime} as it is final and cannot be extended
     *
     * @codeCoverageIgnore
     *
     * @param class-string<\Stringable> $class
     * @param list<string> $strategies
     */
    public function addSafeClass(string $class, array $strategies): void
    {
        $this->originalEscaperRuntime->addSafeClass($class, $strategies);
    }

    /**
     * Mimics the public API of {@see EscaperRuntime} as it is final and cannot be extended
     *
     * Additionally caches the escaped value to increase the performance.
     * Caching other types than `string` and `Stringable` brings no value, as the checks for those types cost more than the cache brings benefit.
     * E.g. integers and floats are rarely occuring with the same value more than once.
     * Changing the logic here should be proven with performance measuering tools like Blackfire.
     *
     * @throws RuntimeError
     */
    public function escape(mixed $string, string $strategy = 'html', ?string $charset = null, bool $autoescape = false): mixed
    {
        $isString = \is_string($string);
        if ($isString && isset(self::$escapeCache[$string][$strategy])) {
            return self::$escapeCache[$string][$strategy];
        }

        $isStringAble = $string instanceof \Stringable;
        if ($isStringAble) {
            $hash = spl_object_hash($string);
            if (isset(self::$escapeCache[$hash][$strategy])) {
                return self::$escapeCache[$hash][$strategy];
            }
        }

        $result = $this->originalEscaperRuntime->escape($string, $strategy, $charset, $autoescape);

        if ($isStringAble) {
            self::$escapeCache[$hash][$strategy] = $result;

            return $result;
        }

        if (!$isString) {
            return $result;
        }

        self::$escapeCache[$string][$strategy] = $result;

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
