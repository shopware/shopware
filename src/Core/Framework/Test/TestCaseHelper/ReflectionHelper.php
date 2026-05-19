<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\TestCaseHelper;

use Shopware\Core\Framework\Feature;

/**
 * @internal
 *
 * @deprecated tag:v6.8.0 - will be removed without replacement. Use native replacements directly in tests.
 */
class ReflectionHelper
{
    /**
     * @param class-string<object> $className
     *
     * @deprecated tag:v6.8.0 - will be removed without replacement. Use native \ReflectionMethod directly in tests.
     */
    public static function getMethod(string $className, string $methodName): \ReflectionMethod
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.8.0.0', 'Use native \ReflectionMethod directly'));

        return (new \ReflectionClass($className))->getMethod($methodName);
    }

    /**
     * @param class-string<object> $className
     *
     * @deprecated tag:v6.8.0 - will be removed without replacement. Use native \ReflectionProperty directly in tests.
     */
    public static function getProperty(string $className, string $propertyName): \ReflectionProperty
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.8.0.0', 'Use native \ReflectionProperty directly'));

        return (new \ReflectionClass($className))->getProperty($propertyName);
    }

    /**
     * @deprecated tag:v6.8.0 - will be removed without replacement. Use native \ReflectionProperty directly in tests.
     */
    public static function getPropertyValue(object $object, string $propertyName): mixed
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.8.0.0', 'Use native \ReflectionProperty directly'));

        return (new \ReflectionProperty($object::class, $propertyName))->getValue($object);
    }

    /**
     * @param class-string<object> $className
     *
     * @deprecated tag:v6.8.0 - will be removed without replacement. Use native (new \ReflectionClass($className))->getFileName() directly in tests.
     */
    public static function getFileName(string $className): string|false
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.8.0.0', 'Use native (new \ReflectionClass($className))->getFileName() directly'));

        return (new \ReflectionClass($className))->getFileName();
    }
}
