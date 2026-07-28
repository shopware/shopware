<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Mcp;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\McpToolsetSessionStorage;
use Symfony\Component\Clock\NativeClock;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(McpToolsetSessionStorage::class)]
class McpToolsetSessionStorageTest extends TestCase
{
    public function testEnableStoresToolsetForSession(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('executeStatement')
            ->with(
                static::stringContains('INSERT IGNORE INTO `mcp_toolset_session`'),
                static::callback(static fn (array $params): bool => $params['sessionId'] === 'session-a' && $params['toolsetName'] === 'shopware-entity' && \is_string($params['createdAt'])),
            );

        $storage = new McpToolsetSessionStorage($connection, new NativeClock());
        $storage->enable('session-a', 'shopware-entity');
    }

    public function testEnabledToolsetsReturnsSortedNamesForSession(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('fetchFirstColumn')
            ->with(
                static::stringContains('WHERE `session_id` = :sessionId'),
                ['sessionId' => 'session-a'],
            )
            ->willReturn(['shopware-entity', 'shopware-media']);

        $storage = new McpToolsetSessionStorage($connection, new NativeClock());

        static::assertSame(['shopware-entity', 'shopware-media'], $storage->enabledToolsets('session-a'));
    }

    public function testDeleteForSessionRemovesOnlyThatSession(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('executeStatement')
            ->with(
                static::stringContains('DELETE FROM `mcp_toolset_session`'),
                ['sessionId' => 'session-a'],
            );

        $storage = new McpToolsetSessionStorage($connection, new NativeClock());
        $storage->deleteForSession('session-a');
    }

    public function testSessionIdsReturnsDistinctSessionIds(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('fetchFirstColumn')
            ->with(static::stringContains('SELECT DISTINCT `session_id` FROM `mcp_toolset_session`'))
            ->willReturn(['session-a', 'session-b']);

        $storage = new McpToolsetSessionStorage($connection, new NativeClock());

        static::assertSame(['session-a', 'session-b'], $storage->sessionIds());
    }
}
