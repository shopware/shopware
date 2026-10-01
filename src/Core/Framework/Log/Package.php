<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Log;

/**
 * @internal
 *
 * @phpstan-type PackageString 'inventory'|'checkout'|'after-sales'|'framework'|'data-services'|'innovation'|'discovery'|'b2b'|'fundamentals@framework'|'fundamentals@discovery'|'fundamentals@checkout'|'fundamentals@after-sales'|'saas-infrastructure'
 *
 * # Important
 * if the above valid types / domains are changed, please also update them here:
 * src/Administration/Resources/app/administration/eslint-rules/core-rules/require-package-annotation.js
 */
#[Package('framework')]
#[\Attribute(\Attribute::TARGET_CLASS)]
final class Package
{
    public const PACKAGE_TRACE_ATTRIBUTE_KEY = 'pTrace';

    /**
     * Cache of the resolved package name per class. The `#[Package]` attribute is immutable metadata,
     * so the reflection only needs to run once per class (this is invoked per Monolog record).
     *
     * @var array<string, string|null>
     */
    private static array $packageCache = [];

    /**
     * @param PackageString $package
     */
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
        if (\array_key_exists($class, self::$packageCache)) {
            return self::$packageCache[$class];
        }

        $reflection = new \ReflectionClass($class);

        $attrs = $reflection->getAttributes(Package::class);

        return self::$packageCache[$class] = $attrs !== [] ? ($attrs[0]->getArguments()[0] ?? null) : null;
    }
}
