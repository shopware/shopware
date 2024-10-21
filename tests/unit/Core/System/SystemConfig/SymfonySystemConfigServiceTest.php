<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\SystemConfig;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\System\SystemConfig\SymfonySystemConfigService;

/**
 * @internal
 */
#[CoversClass(SymfonySystemConfigService::class)]
class SymfonySystemConfigServiceTest extends TestCase
{
    public function testGetConfig(): void
    {
        $config = [
            'default' => [
                'key' => 'value',
            ],
            'salesChannelId' => [
                'key' => 'value',
            ],
        ];

        $service = new SymfonySystemConfigService($config);

        static::assertEquals($config['default'], $service->getConfig());
        static::assertEquals($config['salesChannelId'], $service->getConfig('salesChannelId'));
    }

    public function testGet(): void
    {
        $config = [
            'default' => [
                'key' => 'value',
            ],
            'salesChannelId' => [
                'key' => 'value2',
            ],
        ];

        $service = new SymfonySystemConfigService($config);

        static::assertEquals('value', $service->get('key'));
        static::assertEquals('value2', $service->get('key', 'salesChannelId'));
        static::assertTrue($service->has('key'));
        static::assertFalse($service->has('nonExistentKey'));
        static::assertNull($service->get('nonExistentKey'));
        static::assertNull($service->get('nonExistentKey', 'salesChannelId'));
    }

    public function testHas(): void
    {
        $config = [
            'default' => [
                'key' => 'value',
            ],
            'salesChannelId' => [
                'key' => 'value',
            ],
        ];

        $service = new SymfonySystemConfigService($config);

        static::assertTrue($service->has('key'));
        static::assertFalse($service->has('nonExistentKey'));
    }

    public function testOverride(): void
    {
        $config = [
            'default' => [
                'key' => 'value',
                'onlyDefault' => 'value',
            ],
            'salesChannelId' => [
                'key' => 'value2',
            ],
        ];

        $service = new SymfonySystemConfigService($config);

        $merged = [
            'key' => null,
        ];

        static::assertEquals(['key' => 'value', 'onlyDefault' => 'value'], $service->override($merged, null, inherit: false, nesting: false));
        static::assertEquals(['key' => 'value2'], $service->override($merged, 'salesChannelId', inherit: false, nesting: false));
        static::assertEquals(['key' => 'value2', 'onlyDefault' => 'value'], $service->override($merged, 'salesChannelId', nesting: false));
    }

    public function testOverrideNested(): void
    {
        $config = [
            'default' => [
                'key' => 'value',
                'nested.key' => 'value',
                'first.key' => 'test',
            ],
            'salesChannelId' => [
                'key' => 'value2',
                'nested.key' => 'value2',
                'second.key' => 'test',
            ],
        ];

        $service = new SymfonySystemConfigService($config);

        $merged = [
            'key' => null,
            'nested' => [
                'key' => null,
            ],
        ];

        static::assertEquals(['key' => 'value', 'nested' => ['key' => 'value'], 'first' => ['key' => 'test']], $service->override($merged, null, inherit: false));
        static::assertEquals(['key' => 'value2', 'nested' => ['key' => 'value2'], 'second' => ['key' => 'test']], $service->override($merged, 'salesChannelId', inherit: false));
        static::assertEquals(['key' => 'value2', 'nested' => ['key' => 'value2'], 'first' => ['key' => 'test'], 'second' => ['key' => 'test']], $service->override($merged, 'salesChannelId'));
    }
}
