<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Log;

/**
 * @internal
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
#[Package('core')]
final class Package
{
    public const PACKAGE_TRACE_ATTRIBUTE_KEY = 'pTrace';

    public function __construct(public string $package)
    {
    }

    public static function getPackageName(string $class, bool $tryParentClass = false): ?string
    {
        if (!class_exists($class)) {
            return null;
        }

        $package = self::evaluateAttributes($class);
        if ($package || !$tryParentClass) {
            return $package;
        }

        $parentClass = get_parent_class($class);
        if ($parentClass && $package = self::evaluateAttributes($parentClass)) {
            return $package;
        }

        return null;
    }

    /**
     * @param class-string $class
     */
    private static function evaluateAttributes(string $class): ?string
    {
        $reflection = new \ReflectionClass($class);

        $attrs = $reflection->getAttributes(Package::class);

        if (!empty($attrs)) {
            return $attrs[0]->getArguments()[0] ?? null;
        }

        return null;
    }
}
