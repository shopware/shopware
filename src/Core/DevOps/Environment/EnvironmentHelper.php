<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\Environment;

class EnvironmentHelper
{
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
        return $_SERVER[$key] ?? $_ENV[$key] ?? $default;
    }

    public static function hasVariable(string $key): bool
    {
        return \array_key_exists($key, $_SERVER) || \array_key_exists($key, $_ENV);
    }
}
