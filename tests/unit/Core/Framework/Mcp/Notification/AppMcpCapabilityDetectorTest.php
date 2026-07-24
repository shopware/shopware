<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Mcp\Notification;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Feature\AppFeatureStorage;
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
        $appId = Uuid::randomHex();
        $storage = $this->createMock(AppFeatureStorage::class);
        $storage->expects($this->exactly(3))
            ->method('forApp')
            ->willReturnCallback(static function (string $queriedAppId, string $configClass) use ($appId): array {
                static::assertSame($appId, $queriedAppId);

                static::assertContains($configClass, [McpToolConfig::class, McpResourceConfig::class, McpPromptConfig::class]);

                return $configClass === McpResourceConfig::class ? [] : [new \stdClass()];
            });

        $detector = new AppMcpCapabilityDetector($storage);
        $capabilities = $detector->persistedForApp($appId);

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
