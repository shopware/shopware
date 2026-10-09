<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Mcp\Loader;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Feature\AppFeature;
use Shopware\Core\Framework\App\Feature\AppFeatureStorage;
use Shopware\Core\Framework\App\Feature\TranslatedString;
use Shopware\Core\Framework\App\Mcp\Feature\McpToolConfig;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\Loader\AppMcpPrivilegeProvider;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(AppMcpPrivilegeProvider::class)]
class AppMcpPrivilegeProviderTest extends TestCase
{
    private AppFeatureStorage&Stub $storage;

    private AppMcpPrivilegeProvider $provider;

    protected function setUp(): void
    {
        $this->storage = static::createStub(AppFeatureStorage::class);
        $this->provider = new AppMcpPrivilegeProvider($this->storage);
    }

    public function testEachToolMapsToItsRequiredPrivileges(): void
    {
        $this->storage->method('forActiveApps')->willReturn([
            $this->feature('sync-orders', ['order:read', 'order:update'], 'my-erp'),
            $this->feature('erp-status', [], 'my-erp'),
        ]);

        static::assertSame(
            [
                'my-erp-sync-orders' => ['order:read', 'order:update'],
                'my-erp-erp-status' => [],
            ],
            $this->provider->getAppToolPrivileges(),
        );
    }

    public function testEachToolIsGroupedUnderItsApp(): void
    {
        $this->storage->method('forActiveApps')->willReturn([
            $this->feature('sync-orders', [], 'my-erp'),
            $this->feature('read-stock', [], 'my-erp'),
            $this->feature('do-thing', [], 'other-app'),
        ]);

        static::assertSame(
            [
                'my-erp-sync-orders' => 'my-erp',
                'my-erp-read-stock' => 'my-erp',
                'other-app-do-thing' => 'other-app',
            ],
            $this->provider->getAppToolGroups(),
        );
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
