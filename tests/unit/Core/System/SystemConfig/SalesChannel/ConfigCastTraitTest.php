<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\SystemConfig\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SystemConfig\SalesChannel\ConfigCastTrait;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ConfigCastTrait::class)]
class ConfigCastTraitTest extends TestCase
{
    /**
     * @param array<string, mixed> $config
     */
    #[DataProvider('boolValueProvider')]
    public function testBoolValue(array $config, bool $expected): void
    {
        static::assertSame($expected, ConfigCastFixture::castBool($config, 'key'));
    }

    public static function boolValueProvider(): \Generator
    {
        yield 'missing key falls back to false' => [[], false];
        yield 'null value falls back to false' => [['key' => null], false];
        yield 'true stays true' => [['key' => true], true];
        yield 'false stays false' => [['key' => false], false];
        yield 'truthy scalar is cast to true' => [['key' => 1], true];
        yield 'falsy string zero is cast to false' => [['key' => '0'], false];
        yield 'unexpected empty array is cast to false' => [['key' => []], false];
    }

    /**
     * @param array<string, mixed> $config
     */
    #[DataProvider('intValueProvider')]
    public function testIntValue(array $config, int $expected): void
    {
        static::assertSame($expected, ConfigCastFixture::castInt($config, 'key'));
    }

    public static function intValueProvider(): \Generator
    {
        yield 'missing key falls back to zero' => [[], 0];
        yield 'null value falls back to zero' => [['key' => null], 0];
        yield 'int stays int' => [['key' => 12], 12];
        yield 'numeric string is cast to int' => [['key' => '12'], 12];
        yield 'float is truncated to int' => [['key' => 12.9], 12];
        yield 'non-numeric string falls back to zero' => [['key' => 'abc'], 0];
        yield 'bool is not numeric and falls back to zero' => [['key' => true], 0];
        yield 'unexpected array falls back to zero' => [['key' => ['nested' => 1]], 0];
    }

    /**
     * @param array<string, mixed> $config
     */
    #[DataProvider('stringValueProvider')]
    public function testStringValue(array $config, string $expected): void
    {
        static::assertSame($expected, ConfigCastFixture::castString($config, 'key'));
    }

    public static function stringValueProvider(): \Generator
    {
        yield 'missing key falls back to empty string' => [[], ''];
        yield 'null value falls back to empty string' => [['key' => null], ''];
        yield 'string stays string' => [['key' => 'index,follow'], 'index,follow'];
        yield 'int is cast to string' => [['key' => 12], '12'];
        yield 'bool true is cast to "1"' => [['key' => true], '1'];
        yield 'unexpected array falls back to empty string' => [['key' => ['nested']], ''];
    }
}

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
