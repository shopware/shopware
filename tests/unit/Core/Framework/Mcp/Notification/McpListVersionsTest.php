<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Mcp\Notification;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\Notification\McpListChangedNotificationSet;
use Shopware\Core\Framework\Mcp\Notification\McpListVersions;
use Symfony\Component\Clock\MockClock;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(McpListVersions::class)]
class McpListVersionsTest extends TestCase
{
    public function testBumpIncrementsEachChangedListOnce(): void
    {
        $bumped = [];

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->exactly(2))
            ->method('executeStatement')
            ->willReturnCallback(static function (string $sql, array $params) use (&$bumped): int {
                static::assertStringContainsString('ON DUPLICATE KEY UPDATE `version` = `version` + 1', $sql);
                static::assertSame('2026-10-07 10:00:00.000', $params['now']);
                $bumped[] = $params['list'];

                return 1;
            });

        $versions = new McpListVersions($connection, new MockClock('2026-10-07 10:00:00'));
        $versions->bump(new McpListChangedNotificationSet(tools: true, resources: false, prompts: true));

        static::assertSame(['tools', 'prompts'], $bumped);
    }

    public function testBumpWithoutChangesWritesNothing(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->never())->method('executeStatement');

        (new McpListVersions($connection, new MockClock()))->bump(McpListChangedNotificationSet::none());
    }

    public function testCurrentDefaultsMissingListsToZeroAndIgnoresUnknownRows(): void
    {
        $connection = static::createStub(Connection::class);
        $connection->method('fetchAllKeyValue')->willReturn(['tools' => '3', 'unknown' => '9']);

        static::assertSame(
            ['tools' => 3, 'resources' => 0, 'prompts' => 0],
            (new McpListVersions($connection, new MockClock()))->current(),
        );
    }
}
