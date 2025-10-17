<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\TestCaseHelper;

/**
 * @internal
 */
class ReflectionHelper
{
    /**
     * @param class-string<object> $className
     */
    public static function getMethod(string $className, string $methodName): \ReflectionMethod
    {
        return (new \ReflectionClass($className))->getMethod($methodName);
    }

    /**
     * @param class-string<object> $className
     */
    public static function getProperty(string $className, string $propertyName): \ReflectionProperty
    {
        return (new \ReflectionClass($className))->getProperty($propertyName);
    }

    public static function getPropertyValue(object $object, string $propertyName): mixed
    {
        return static::getProperty($object::class, $propertyName)->getValue($object);
    }

    /**
     * @param class-string<object> $className
     */
    public static function getFileName(string $className): string|false
    {
        return (new \ReflectionClass($className))->getFileName();
    }
}
