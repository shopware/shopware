<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Mcp\Notification;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Feature\AppFeature;
use Shopware\Core\Framework\App\Feature\AppFeatureStorage;
use Shopware\Core\Framework\App\Feature\TranslatedString;
use Shopware\Core\Framework\App\Mcp\Feature\McpPromptConfig;
use Shopware\Core\Framework\App\Mcp\Feature\McpResourceConfig;
use Shopware\Core\Framework\App\Mcp\Feature\McpToolConfig;
use Shopware\Core\Framework\App\Mcp\Mcp;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\Notification\AppMcpCapabilityDetector;
use Shopware\Core\Framework\Mcp\Notification\McpListChangedNotificationSet;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(AppMcpCapabilityDetector::class)]
#[CoversClass(McpListChangedNotificationSet::class)]
class AppMcpCapabilityDetectorTest extends TestCase
{
    public function testDetectsPersistedCapabilitiesForApp(): void
    {
        $config = new McpToolConfig('sync-orders', 'https://app.example.com/mcp/sync-orders', [], null, new TranslatedString([]), new TranslatedString([]));
        $feature = new AppFeature('0189aaaabbbbcccc0000000000000001', 'my-app', true, '0.0.0', true, new \DateTimeImmutable(), $config);

        $storage = $this->createMock(AppFeatureStorage::class);
        $storage->expects($this->exactly(3))
            ->method('forApp')
            ->willReturnCallback(fn (string $appId, string $featureClass): array => match ($featureClass) {
                McpToolConfig::class, McpPromptConfig::class => [$feature],
                McpResourceConfig::class => [],
                default => [],
            });

        $detector = new AppMcpCapabilityDetector($storage);
        $capabilities = $detector->persistedForApp(Uuid::randomHex());

        static::assertTrue($capabilities->tools);
        static::assertFalse($capabilities->resources);
        static::assertTrue($capabilities->prompts);
    }

    public function testDetectsCapabilitiesFromMcpXml(): void
    {
        $detector = new AppMcpCapabilityDetector(static::createStub(AppFeatureStorage::class));
        $capabilities = $detector->fromMcp(Mcp::createFromXmlFile(__DIR__ . '/../../App/Mcp/_fixtures/mcp.xml'));

        static::assertTrue($capabilities->tools);
        static::assertTrue($capabilities->resources);
        static::assertTrue($capabilities->prompts);
    }

    public function testNullMcpXmlHasNoCapabilities(): void
    {
        $detector = new AppMcpCapabilityDetector(static::createStub(AppFeatureStorage::class));
        $capabilities = $detector->fromMcp(null);

        static::assertFalse($capabilities->hasChanges());
    }

    public function testEmptyMcpXmlHasNoCapabilities(): void
    {
        $detector = new AppMcpCapabilityDetector(static::createStub(AppFeatureStorage::class));
        $capabilities = $detector->fromMcp(Mcp::createFromXmlFile(__DIR__ . '/../../App/Mcp/_fixtures/mcp_empty.xml'));

        static::assertFalse($capabilities->hasChanges());
    }
}
