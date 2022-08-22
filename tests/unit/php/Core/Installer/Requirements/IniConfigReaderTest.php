<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Installer\Requirements;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Installer\Requirements\IniConfigReader;

/**
 * @internal
 * @covers \Shopware\Core\Installer\Requirements\IniConfigReader
 */
class IniConfigReaderTest extends TestCase
{
    /**
     * @param string|false $configValue
     *
     * @dataProvider configProvider
     */
    public function testGet(string $key, $configValue, string $expectedValue): void
    {
        \ini_set($key, (string) $configValue);

        $reader = new IniConfigReader();
        static::assertSame($expectedValue, $reader->get($key));

        \ini_restore($key);
    }

    public function configProvider(): \Generator
    {
        yield 'max_execution_time' => [
            'max_execution_time',
            '30',
            '30',
        ];

        yield 'memory_limit' => [
            'memory_limit',
            '512M',
            '512M',
        ];

        yield 'not set value' => [
            'max_execution_time',
            false,
            '',
        ];
    }
}
