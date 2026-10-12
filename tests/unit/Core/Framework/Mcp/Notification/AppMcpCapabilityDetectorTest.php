<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Mcp\Notification;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
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
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->exactly(3))
            ->method('fetchOne')
            ->willReturnOnConsecutiveCalls('1', false, '1');

        $detector = new AppMcpCapabilityDetector($connection);
        $capabilities = $detector->persistedForApp(Uuid::randomHex());

        static::assertTrue($capabilities->tools);
        static::assertFalse($capabilities->resources);
        static::assertTrue($capabilities->prompts);
    }

    public function testDetectsCapabilitiesFromMcpXml(): void
    {
        $detector = new AppMcpCapabilityDetector(static::createStub(Connection::class));
        $capabilities = $detector->fromMcp(Mcp::createFromXmlFile(__DIR__ . '/../../App/Mcp/_fixtures/mcp.xml'));

        static::assertTrue($capabilities->tools);
        static::assertTrue($capabilities->resources);
        static::assertTrue($capabilities->prompts);
    }

    public function testNullMcpXmlHasNoCapabilities(): void
    {
        $detector = new AppMcpCapabilityDetector(static::createStub(Connection::class));
        $capabilities = $detector->fromMcp(null);

        static::assertFalse($capabilities->hasChanges());
    }

    public function testEmptyMcpXmlHasNoCapabilities(): void
    {
        $detector = new AppMcpCapabilityDetector(static::createStub(Connection::class));
        $capabilities = $detector->fromMcp(Mcp::createFromXmlFile(__DIR__ . '/../../App/Mcp/_fixtures/mcp_empty.xml'));

        static::assertFalse($capabilities->hasChanges());
    }
}
