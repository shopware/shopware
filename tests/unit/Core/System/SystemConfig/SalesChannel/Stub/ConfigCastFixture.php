<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\SystemConfig\SalesChannel\Stub;

use Shopware\Core\System\SystemConfig\SalesChannel\ConfigCastTrait;

/**
 * @internal exposes the private trait methods for the test
 */
class ConfigCastFixture
{
    use ConfigCastTrait;

    /**
     * @param array<string, mixed> $config
     */
    public static function castBool(array $config, string $key): bool
    {
        return self::boolValue($config, $key);
    }

    /**
     * @param array<string, mixed> $config
     */
    public static function castInt(array $config, string $key): int
    {
        return self::intValue($config, $key);
    }

    /**
     * @param array<string, mixed> $config
     */
    public static function castString(array $config, string $key): string
    {
        return self::stringValue($config, $key);
    }
}
