<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Telemetry;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Shopware\Core\Framework\App\Feature\AppFeature;
use Shopware\Core\Framework\App\Feature\AppFeatureStorage;
use Shopware\Core\Framework\App\Telemetry\AppTelemetryConfig;
use Shopware\Core\Framework\App\Telemetry\AppTelemetryConfigLoader;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(AppTelemetryConfigLoader::class)]
class AppTelemetryConfigLoaderTest extends TestCase
{
    public function testMergesConfigsAcrossApps(): void
    {
        $loader = $this->loader([
            new AppTelemetryConfig(
                labelValues: [['metric' => 'order.placed.count', 'label' => 'payment_method', 'values' => ['a', 'b']]],
                mappings: ['entity_group' => ['swag_a_*' => 'blog']],
            ),
            new AppTelemetryConfig(
                labelValues: [['metric' => 'order.placed.count', 'label' => 'payment_method', 'values' => ['b', 'c']]],
                mappings: ['entity_group' => ['swag_b_*' => 'cms']],
            ),
        ]);

        $merged = $loader->load();

        static::assertSame(['a', 'b', 'c'], $merged['labelValues']['order.placed.count']['payment_method']);
        static::assertSame(['swag_a_*' => 'blog', 'swag_b_*' => 'cms'], $merged['mappings']['entity_group']);
    }

    public function testCrossAppMappingCollisionKeepsFirstValue(): void
    {
        $loader = $this->loader([
            new AppTelemetryConfig(mappings: ['entity_group' => ['shared_key' => 'first']]),
            new AppTelemetryConfig(mappings: ['entity_group' => ['shared_key' => 'second']]),
        ]);

        static::assertSame(['shared_key' => 'first'], $loader->load()['mappings']['entity_group']);
    }

    public function testFailsSoftOnStorageErrorAndLogsOnce(): void
    {
        $storage = static::createStub(AppFeatureStorage::class);
        $storage->method('forActiveApps')->willThrowException(new \RuntimeException('db down'));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('warning')
            ->with(static::stringContains('db down'));

        $loader = new AppTelemetryConfigLoader(fn () => $storage, $logger);

        static::assertSame(['labelValues' => [], 'mappings' => []], $loader->load());
        // the failure is memoized like a successful read: the process keeps the compiled config
        static::assertSame(['labelValues' => [], 'mappings' => []], $loader->load());
    }

    public function testResolvesTheStorageOnlyWhenLoading(): void
    {
        $resolved = new \stdClass();
        $resolved->count = 0;
        $storage = static::createStub(AppFeatureStorage::class);
        $storage->method('forActiveApps')->willReturn([]);

        $loader = new AppTelemetryConfigLoader(function () use ($resolved, $storage): AppFeatureStorage {
            ++$resolved->count;

            return $storage;
        }, new NullLogger());

        static::assertSame(0, $resolved->count);

        $loader->load();

        static::assertSame(1, $resolved->count);
    }

    public function testLoadsOncePerProcess(): void
    {
        $storage = $this->createMock(AppFeatureStorage::class);
        $storage->expects($this->once())->method('forActiveApps')->willReturn([]);

        $loader = new AppTelemetryConfigLoader(fn () => $storage, new NullLogger());

        $loader->load();
        $loader->load();
    }

    /**
     * @param list<AppTelemetryConfig> $configs
     */
    private function loader(array $configs): AppTelemetryConfigLoader
    {
        $features = [];
        foreach ($configs as $i => $config) {
            $features[] = new AppFeature(
                appId: \sprintf('app-id-%d', $i),
                appName: \sprintf('App%d', $i),
                appActive: true,
                appVersion: '1.0.0',
                appHasSecret: false,
                createdAt: new \DateTimeImmutable('2026-01-01 00:00:00'),
                config: $config,
            );
        }

        $storage = static::createStub(AppFeatureStorage::class);
        $storage->method('forActiveApps')->willReturn($features);

        return new AppTelemetryConfigLoader(fn () => $storage, new NullLogger());
    }
}
