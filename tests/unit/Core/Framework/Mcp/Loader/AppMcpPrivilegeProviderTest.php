<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Mcp\Loader;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Shopware\Core\Framework\App\Feature\AppFeature;
use Shopware\Core\Framework\App\Feature\AppFeatureStorage;
use Shopware\Core\Framework\App\Feature\TranslatedString;
use Shopware\Core\Framework\App\Mcp\Feature\McpToolConfig;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\Loader\AppMcpPrivilegeProvider;

/**
 * @internal
 */
#[CoversClass(AppMcpPrivilegeProvider::class)]
#[Package('framework')]
class AppMcpPrivilegeProviderTest extends TestCase
{
    public function testReturnsEmptyMapWhenNoFeatures(): void
    {
        $storage = static::createStub(AppFeatureStorage::class);
        $storage->method('forActiveApps')->willReturn([]);

        $provider = new AppMcpPrivilegeProvider($storage, new NullLogger());

        static::assertSame([], $provider->getAppToolPrivileges());
    }

    public function testMapsRequiredPrivilegesByPrefixedToolName(): void
    {
        $storage = static::createStub(AppFeatureStorage::class);
        $storage->method('forActiveApps')->willReturn([
            $this->feature('sync-orders', ['order:read', 'order:update'], 'my-erp'),
            $this->feature('erp-status', ['system:read'], 'my-erp'),
        ]);

        $provider = new AppMcpPrivilegeProvider($storage, new NullLogger());

        static::assertSame(
            [
                'my-erp-sync-orders' => ['order:read', 'order:update'],
                'my-erp-erp-status' => ['system:read'],
            ],
            $provider->getAppToolPrivileges(),
        );
    }

    public function testIncludesToolsWithoutRequiredPrivilegesAsAnEmptyList(): void
    {
        $storage = static::createStub(AppFeatureStorage::class);
        $storage->method('forActiveApps')->willReturn([
            $this->feature('no-priv', [], 'my-erp'),
            $this->feature('with-priv', ['entity:read'], 'my-erp'),
        ]);

        $provider = new AppMcpPrivilegeProvider($storage, new NullLogger());

        static::assertSame([
            'my-erp-no-priv' => [],
            'my-erp-with-priv' => ['entity:read'],
        ], $provider->getAppToolPrivileges());
    }

    public function testReturnsEmptyMapAndLogsErrorWhenStorageThrows(): void
    {
        $storage = static::createStub(AppFeatureStorage::class);
        $storage->method('forActiveApps')->willThrowException(new \RuntimeException('DB down'));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('error')
            ->with('Failed to load app MCP tool privileges', static::arrayHasKey('exception'));

        $provider = new AppMcpPrivilegeProvider($storage, $logger);

        static::assertSame([], $provider->getAppToolPrivileges());
    }

    /**
     * @param list<string> $requiredPrivileges
     *
     * @return AppFeature<McpToolConfig>
     */
    private function feature(string $name, array $requiredPrivileges, string $appName): AppFeature
    {
        $config = new McpToolConfig($name, 'https://app.example.com/mcp/' . $name, $requiredPrivileges, null, new TranslatedString([]), new TranslatedString([]));

        return new AppFeature('0189aaaabbbbcccc0000000000000001', $appName, true, '0.0.0', true, new \DateTimeImmutable(), $config);
    }
}
