<?php declare(strict_types=1);

namespace Shopware\Core\Framework\FeatureFlag;

class FeatureConfig
{
    private static $flags = [];

    public static function addFlag(string $flagName): void
    {
        self::$flags[$flagName] = false;
    }

    public static function activate(string $flagName): void
    {
        self::$flags[$flagName] = true;
    }

    public static function getAll(): array
    {
        return self::$flags;
    }

    public static function isActive(string $flagName): bool
    {
        if(!isset(self::$flags[$flagName])) {
            throw new \RuntimeException(sprintf('Unable to retrieve flag %s, not registered', $flagName));
        }

        return self::$flags[$flagName];
    }
}
