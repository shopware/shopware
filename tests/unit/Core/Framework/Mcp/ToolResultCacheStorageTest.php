<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Mcp;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\Result\McpToolResultPointerSigner;
use Shopware\Core\Framework\Mcp\Result\McpToolResultStoredFormat;
use Shopware\Core\Framework\Mcp\ToolResultCacheStorage;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Clock\NativeClock;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ToolResultCacheStorage::class)]
#[CoversClass(McpToolResultStoredFormat::class)]
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

        $storage = new ToolResultCacheStorage($connection, new NativeClock(), new McpToolResultPointerSigner('secret', new NativeClock()));
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

        $storage = new ToolResultCacheStorage($connection, new NativeClock(), new McpToolResultPointerSigner('secret', new NativeClock()));
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
                static::stringContains('`created_at` >= :since'),
                [
                    'id' => Uuid::fromHexToBytes($id),
                    'sessionId' => 'session-abc',
                    // A plain id lives no longer than a signed pointer: one hour before the clock.
                    'since' => '2026-10-08 11:00:00.000',
                ],
            )
            ->willReturn(['content' => '{"foo": "bar"}', 'mime_type' => 'application/json']);

        $clock = new MockClock('2026-10-08 12:00:00');
        $storage = new ToolResultCacheStorage($connection, $clock, new McpToolResultPointerSigner('secret', $clock));
        $result = $storage->read($id, 'session-abc');

        static::assertNotNull($result);
        static::assertSame('{"foo": "bar"}', $result['content']);
        static::assertSame('application/json', $result['mimeType']);
    }

    public function testReadReturnsNullForSessionMismatch(): void
    {
        $id = Uuid::randomHex();

        $connection = static::createStub(Connection::class);
        $connection->method('fetchAssociative')->willReturn(false);

        $storage = new ToolResultCacheStorage($connection, new NativeClock(), new McpToolResultPointerSigner('secret', new NativeClock()));
        $result = $storage->read($id, 'other-session');

        static::assertNull($result);
    }

    public function testReadReturnsNullForAMalformedIdWithoutQuerying(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->never())->method('fetchAssociative');

        $storage = new ToolResultCacheStorage($connection, new NativeClock(), new McpToolResultPointerSigner('secret', new NativeClock()));

        static::assertNull($storage->read('not-a-uuid', 'session-abc'));
    }

    public function testReadReturnsNullForUnknownId(): void
    {
        $id = Uuid::randomHex();

        $connection = static::createStub(Connection::class);
        $connection->method('fetchAssociative')->willReturn(false);

        $storage = new ToolResultCacheStorage($connection, new NativeClock(), new McpToolResultPointerSigner('secret', new NativeClock()));

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

        $storage = new ToolResultCacheStorage($connection, new NativeClock(), new McpToolResultPointerSigner('secret', new NativeClock()));
        $storage->deleteForSession('session-abc');
    }

    public function testStoreForReturnsAPointerThatReadsBackForTheSamePrincipalOnly(): void
    {
        $rows = [];

        $connection = static::createStub(Connection::class);
        $connection->method('insert')->willReturnCallback(static function (string $table, array $row) use (&$rows): int {
            $rows[Uuid::fromBytesToHex($row['id'])] = $row;

            return 1;
        });
        $connection->method('fetchAssociative')->willReturnCallback(static function (string $sql, array $params) use (&$rows): array|false {
            $row = $rows[Uuid::fromBytesToHex($params['id'])] ?? null;

            return $row === null ? false : ['content' => $row['content'], 'mime_type' => $row['mime_type']];
        });

        $storage = new ToolResultCacheStorage($connection, new NativeClock(), new McpToolResultPointerSigner('secret', new NativeClock()));
        $pointer = $storage->storeFor('admin:integration-a:', '{"data": 1}');

        static::assertStringStartsWith('shopware://tool-result/', $pointer->uri());
        static::assertSame('', array_values($rows)[0]['session_id'], 'a call without MCP session stores the row without one');
        static::assertSame(['content' => '{"data": 1}', 'mimeType' => 'application/json'], $storage->readFor($pointer->token, 'admin:integration-a:'));
        static::assertNull($storage->readFor($pointer->token, 'admin:integration-b:'));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function storedMimeTypeProvider(): iterable
    {
        yield 'a legacy envelope, as every row written before the format was recorded' => ['application/json', 'application/json'];
        yield 'the plain data of the spec format' => [McpToolResultStoredFormat::Spec->value, 'application/json'];
        yield 'a media type of another caller' => ['text/plain', 'text/plain'];
    }

    #[DataProvider('storedMimeTypeProvider')]
    public function testReadForServesEveryStoredFormatAsJson(string $storedMimeType, string $servedMimeType): void
    {
        $signer = new McpToolResultPointerSigner('secret', new NativeClock());

        $connection = static::createStub(Connection::class);
        $connection->method('fetchAssociative')->willReturn(['content' => '{"a":1}', 'mime_type' => $storedMimeType]);

        $storage = new ToolResultCacheStorage($connection, new NativeClock(), $signer);

        static::assertSame(
            ['content' => '{"a":1}', 'mimeType' => $servedMimeType],
            $storage->readFor($signer->sign(Uuid::randomHex(), 'p')->token, 'p'),
        );
    }

    public function testReadForReturnsNullWhenTheRowIsGone(): void
    {
        $signer = new McpToolResultPointerSigner('secret', new NativeClock());

        $connection = static::createStub(Connection::class);
        $connection->method('fetchAssociative')->willReturn(false);

        $storage = new ToolResultCacheStorage($connection, new NativeClock(), $signer);

        static::assertNull($storage->readFor($signer->sign(Uuid::randomHex(), 'p')->token, 'p'));
    }

    public function testDeleteOlderThanRemovesRowsAtOrBeforeThresholdInBoundedBatches(): void
    {
        $threshold = new \DateTimeImmutable('2026-09-23 12:00:00.000');

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('executeStatement')
            ->with(
                static::logicalAnd(
                    static::stringContains('created_at'),
                    static::stringContains('<='),
                    static::stringContains('LIMIT'),
                ),
                [
                    'threshold' => '2026-09-23 12:00:00.000',
                    'limit' => 100,
                ],
                [
                    'limit' => ParameterType::INTEGER,
                ],
            )
            ->willReturn(3);

        $storage = new ToolResultCacheStorage($connection, new NativeClock(), new McpToolResultPointerSigner('secret', new NativeClock()));

        static::assertSame(3, $storage->deleteOlderThan($threshold));
    }

    public function testDeleteOlderThanLoopsUntilBatchIsNotFull(): void
    {
        $threshold = new \DateTimeImmutable('2026-09-23 12:00:00.000');

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->exactly(3))
            ->method('executeStatement')
            ->willReturnOnConsecutiveCalls(100, 100, 42);

        $storage = new ToolResultCacheStorage($connection, new NativeClock(), new McpToolResultPointerSigner('secret', new NativeClock()));

        static::assertSame(242, $storage->deleteOlderThan($threshold));
    }
}
