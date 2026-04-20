<?php

declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Twig\Runtime;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
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
     * Mimic the public API of {@see EscaperRuntime} as it is final and cannot be extended
     */
    public function setEscaper(string $strategy, callable $callable): void
    {
        $this->originalEscaperRuntime->setEscaper($strategy, $callable);
    }

    /**
     * Mimic the public API of {@see EscaperRuntime} as it is final and cannot be extended
     *
     * @return array<string, callable(string $string, string $charset): string>
     */
    public function getEscapers(): array
    {
        return $this->originalEscaperRuntime->getEscapers();
    }

    /**
     * Mimic the public API of {@see EscaperRuntime} as it is final and cannot be extended
     *
     * @param array<class-string<\Stringable>, string[]> $safeClasses
     */
    public function setSafeClasses(array $safeClasses = []): void
    {
        $this->originalEscaperRuntime->setSafeClasses($safeClasses);
    }

    /**
     * Mimic the public API of {@see EscaperRuntime} as it is final and cannot be extended
     *
     * @param class-string<\Stringable> $class
     * @param list<string> $strategies
     */
    public function addSafeClass(string $class, array $strategies): void
    {
        $this->originalEscaperRuntime->addSafeClass($class, $strategies);
    }

    /**
     * Mimic the public API of {@see EscaperRuntime} as it is final and cannot be extended
     * Additionally caches the escaped value to increase the performance.
     *
     * @throws RuntimeError
     */
    public function escape(mixed $string, string $strategy = 'html', ?string $charset = null, bool $autoescape = false): mixed
    {
        if (\is_bool($string)) {
            return $string;
        }

        if ($string === null) {
            $string = '';
        }

        if (\is_int($string) || \is_float($string)) {
            $string = (string) $string;
        }

        $isString = \is_string($string);

        if ($isString) {
            if (isset(self::$escapeCache[$string][$strategy])) {
                return self::$escapeCache[$string][$strategy];
            }

            if (Uuid::isValid($string)) {
                self::$escapeCache[$string][$strategy] = $string;

                return $string;
            }
        }

        $result = $this->originalEscaperRuntime->escape($string, $strategy, $charset, $autoescape);

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
