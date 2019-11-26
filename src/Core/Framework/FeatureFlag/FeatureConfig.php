<?php declare(strict_types=1);

namespace Shopware\Core\Framework\FeatureFlag;

class FeatureConfig
{
    private static $flags = [];

    public static function registerFlag(string $flagName, string $envName): void
    {
        self::$flags[$flagName] = $envName;
    }

    public static function getAll(): array
    {
        $flagNames = array_keys(self::$flags);
        $resolvedFlags = [];

        foreach ($flagNames as $flagName) {
            $resolvedFlags[$flagName] = self::isActive($flagName);
        }

        return $resolvedFlags;
    }

    public static function isActive(string $flagName): bool
    {
        if (!isset(self::$flags[$flagName])) {
            throw new \RuntimeException(sprintf('Unable to retrieve flag %s, not registered', $flagName));
        }

        return ($_SERVER[self::$flags[$flagName]] ?? '') === '1';
    }
}
