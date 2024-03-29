<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Log\Monolog;

use Doctrine\DBAL\Connection;
use Monolog\Level;
use Monolog\LogRecord;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Monolog\DoctrineSQLHandler;

/**
 * @internal
 *
 * @package core
 */
#[CoversClass(DoctrineSQLHandler::class)]
class DoctrineSQLHandlerTest extends TestCase
{
    private Connection&MockObject $connection;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(Connection::class);
    }

    public function testWrite(): void
    {
        $this->connection->expects(static::once())->method('insert');

        $handler = new DoctrineSQLHandler($this->connection);

        $record = new LogRecord(
            new \DateTimeImmutable(),
            'business events',
            Level::Error,
            'Some message'
        );

        $handler->handle($record);
    }

    public function testWriteWithException(): void
    {
        $exceptionThrown = null;
        $insertData = null;

        $this->connection->expects(static::exactly(2))->method('insert')
            ->willReturnCallback(function (string $table, array $data = []) use (&$exceptionThrown, &$insertData): void {
                static::assertEquals('log_entry', $table);
                static::assertNotEmpty($data['id']);
                static::assertNotEmpty($data['created_at']);
                unset($data['id']);
                unset($data['created_at']);

                if (!$exceptionThrown instanceof \Exception) {
                    $exceptionThrown = new \Exception('some exception');
                    $insertData = $data;

                    throw $exceptionThrown;
                }

                static::assertEquals([
                    'message' => 'Some message',
                    'level' => 400,
                    'channel' => 'business events',
                    'context' => '[]',
                    'extra' => '[]',
                    'updated_at' => null,
                ], $data);
            });

        $handler = new DoctrineSQLHandler($this->connection);

        $record = new LogRecord(
            new \DateTimeImmutable(),
            'business events',
            Level::Error,
            'Some message',
            [
                'environment' => 'test',
            ],
        );

        $handler->handle($record);
        static::assertNotNull($exceptionThrown);
        static::assertSame('some exception', $exceptionThrown->getMessage());
        static::assertIsArray($insertData);
        static::assertSame([
            'message' => 'Some message',
            'level' => 400,
            'channel' => 'business events',
            'context' => '{"environment":"test"}',
            'extra' => '[]',
            'updated_at' => null,
        ], $insertData);
    }
}
