<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Mcp;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\ToolResultCacheStorage;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\Clock\NativeClock;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ToolResultCacheStorage::class)]
class ToolResultCacheStorageTest extends TestCase
{
    public function testStoreInsertsRowAndReturnsHexUuid(): void
    {
        $capturedRow = [];

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('insert')
            ->with('mcp_tool_result_cache', static::callback(static function (array $row) use (&$capturedRow): bool {
                $capturedRow = $row;

                return true;
            }));

        $storage = new ToolResultCacheStorage($connection, new NativeClock());
        $uuid = $storage->store('session-abc', '{"data": 1}');

        static::assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $uuid);
        static::assertSame('session-abc', $capturedRow['session_id']);
        static::assertSame('application/json', $capturedRow['mime_type']);
        static::assertSame('{"data": 1}', $capturedRow['content']);
        static::assertSame(Uuid::fromHexToBytes($uuid), $capturedRow['id']);
    }

    public function testStoreUsesProvidedMimeType(): void
    {
        $capturedRow = [];

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('insert')
            ->with('mcp_tool_result_cache', static::callback(static function (array $row) use (&$capturedRow): bool {
                $capturedRow = $row;

                return true;
            }));

        $storage = new ToolResultCacheStorage($connection, new NativeClock());
        $storage->store('session-xyz', 'text content', 'text/plain');

        static::assertSame('text/plain', $capturedRow['mime_type']);
    }

    public function testReadReturnsContentForMatchingSession(): void
    {
        $id = Uuid::randomHex();

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('fetchAssociative')
            ->with(
                static::anything(),
                ['id' => Uuid::fromHexToBytes($id), 'sessionId' => 'session-abc'],
            )
            ->willReturn(['content' => '{"foo": "bar"}', 'mime_type' => 'application/json']);

        $storage = new ToolResultCacheStorage($connection, new NativeClock());
        $result = $storage->read($id, 'session-abc');

        static::assertNotNull($result);
        static::assertSame('{"foo": "bar"}', $result['content']);
        static::assertSame('application/json', $result['mimeType']);
    }

    public function testReadReturnsNullForSessionMismatch(): void
    {
        $id = Uuid::randomHex();

        $connection = $this->createMock(Connection::class);
        $connection->method('fetchAssociative')->willReturn(false);

        $storage = new ToolResultCacheStorage($connection, new NativeClock());
        $result = $storage->read($id, 'other-session');

        static::assertNull($result);
    }

    public function testReadReturnsNullForUnknownId(): void
    {
        $id = Uuid::randomHex();

        $connection = $this->createMock(Connection::class);
        $connection->method('fetchAssociative')->willReturn(false);

        $storage = new ToolResultCacheStorage($connection, new NativeClock());

        static::assertNull($storage->read($id, 'session-abc'));
    }

    public function testDeleteForSessionRemovesOnlyThatSession(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('executeStatement')
            ->with(
                static::stringContains('session_id'),
                ['sessionId' => 'session-abc'],
            );

        $storage = new ToolResultCacheStorage($connection, new NativeClock());
        $storage->deleteForSession('session-abc');
    }
}
