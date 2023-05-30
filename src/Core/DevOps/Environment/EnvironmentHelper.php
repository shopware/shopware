<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\Environment;

use Shopware\Core\Framework\Log\Package;

#[Package('core')]
class EnvironmentHelper
{
    /**
     * @var array<int, array<class-string<EnvironmentHelperTransformerInterface>, class-string<EnvironmentHelperTransformerInterface>>>
     */
    private static array $transformers = [];

    /**
     * Reads an env var first from $_SERVER then from $_ENV super globals
     * The caller needs to take care of casting the return value to the appropriate type
     *
     * @param bool|float|int|string|null $default
     *
     * @return bool|float|int|string|null
     */
    public static function getVariable(string $key, $default = null)
    {
        $value = $_SERVER[$key] ?? $_ENV[$key] ?? null;
        $transformerData = new EnvironmentHelperTransformerData($key, $value, $default);

        foreach (self::$transformers as $transformers) {
            /** @var class-string<EnvironmentHelperTransformerInterface> $transformer */
            foreach ($transformers as $transformer) {
                $transformer::transform($transformerData);
            }
        }

        return $transformerData->getValue() ?? $transformerData->getDefault();
    }

    public static function hasVariable(string $key): bool
    {
        return \array_key_exists($key, $_SERVER) || \array_key_exists($key, $_ENV);
    }

    public static function addTransformer(string $transformerClass, int $priority = 0): void
    {
        if (!is_subclass_of($transformerClass, EnvironmentHelperTransformerInterface::class, true)) {
            throw new \InvalidArgumentException(
                sprintf(
                    'Expected class to implement "%1$s" but got "%2$s".',
                    EnvironmentHelperTransformerInterface::class,
                    $transformerClass
                )
            );
        }

        self::$transformers[$priority][$transformerClass] = $transformerClass;

        krsort(self::$transformers, \SORT_NUMERIC);
    }

    public static function removeTransformer(string $transformerClass, int $priority = 0): void
    {
        if (!isset(self::$transformers[$priority][$transformerClass])) {
            return;
        }

        unset(self::$transformers[$priority][$transformerClass]);
    }

    public static function removeAllTransformers(): void
    {
        self::$transformers = [];
    }
}
