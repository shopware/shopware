<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Log;

/**
 * @interal
 * @package core
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final class Package
{
    public const PACKAGE_TRACE_ATTRIBUTE_KEY = 'pTrace';

    public string $package;

    public function __construct(string $package)
    {
        $this->package = $package;
    }

    public static function getPackageName(string $class): ?string
    {
        if (!class_exists($class)) {
            return null;
        }

        $reflection = new \ReflectionClass($class);
        if (!method_exists($reflection, 'getAttributes')) {
            return null;
        }

        $attrs = $reflection->getAttributes(Package::class);

        if (!empty($attrs)) {
            return $attrs[0]->getArguments()[0] ?? null;
        }

        return null;
    }
}
