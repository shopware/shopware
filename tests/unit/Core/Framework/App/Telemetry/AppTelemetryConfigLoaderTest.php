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
    public function testClaimsResolveToTheSnakeCasedAppName(): void
    {
        $loader = $this->loader([
            'SwagBlog' => new AppTelemetryConfig(['entity_group' => ['ce_blog_*', 'swag_blog_post'], 'flow_trigger_group' => ['swag.blog.*']]),
            'SwagPayPal' => new AppTelemetryConfig(['entity_group' => ['swag_paypal_*']]),
        ]);

        static::assertSame([
            'entity_group' => [
                'ce_blog_*' => 'swag_blog',
                'swag_blog_post' => 'swag_blog',
                'swag_paypal_*' => 'swag_pay_pal',
            ],
            'flow_trigger_group' => [
                'swag.blog.*' => 'swag_blog',
            ],
        ], $loader->load());
    }

    public function testCrossAppClaimKeepsTheFirstApp(): void
    {
        $loader = $this->loader([
            'AppA' => new AppTelemetryConfig(['entity_group' => ['shared_*']]),
            'AppB' => new AppTelemetryConfig(['entity_group' => ['shared_*']]),
        ]);

        static::assertSame(['entity_group' => ['shared_*' => 'app_a']], $loader->load());
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

        static::assertSame([], $loader->load());
        // the failure is memoized like a successful read: the process keeps the compiled config
        static::assertSame([], $loader->load());
    }

    public function testResolvesTheStorageOnlyWhenLoadingAndOnce(): void
    {
        $resolved = new \stdClass();
        $resolved->count = 0;
        $storage = $this->createMock(AppFeatureStorage::class);
        $storage->expects($this->once())->method('forActiveApps')->willReturn([]);

        $loader = new AppTelemetryConfigLoader(function () use ($resolved, $storage): AppFeatureStorage {
            ++$resolved->count;

            return $storage;
        }, new NullLogger());

        static::assertSame(0, $resolved->count);

        $loader->load();
        $loader->load();

        static::assertSame(1, $resolved->count);
    }

    /**
     * @param array<string, AppTelemetryConfig> $configsByAppName
     */
    private function loader(array $configsByAppName): AppTelemetryConfigLoader
    {
        $features = [];
        foreach ($configsByAppName as $appName => $config) {
            $features[] = new AppFeature(
                appId: 'app-id-' . $appName,
                appName: $appName,
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
