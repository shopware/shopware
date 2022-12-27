<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Log\Monolog;

use Doctrine\DBAL\Connection;
use Monolog\Logger;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Monolog\DoctrineSQLHandler;

/**
 * @covers \Shopware\Core\Framework\Log\Monolog\DoctrineSQLHandler
 *
 * @internal
 *
 * @phpstan-import-type Record from \Monolog\Logger
 *
 * @package core
 */
class DoctrineSQLHandlerTest extends TestCase
{
    /**
     * @var Connection&MockObject
     */
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(Connection::class);
    }

    public function testWrite(): void
    {
        $this->connection->expects(static::once())->method('insert');

        $handler = new DoctrineSQLHandler($this->connection);

        $record = [
            'message' => 'Some message',
            'context' => [],
            'level' => Logger::ERROR,
            'level_name' => 'ERROR',
            'channel' => 'business events',
            'datetime' => new \DateTimeImmutable(),
            'extra' => [],
        ];

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

                if ($exceptionThrown === null) {
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

        $record = [
            'message' => 'Some message',
            'context' => [
                'environment' => 'test',
            ],
            'level' => Logger::ERROR,
            'level_name' => 'ERROR',
            'channel' => 'business events',
            'datetime' => new \DateTimeImmutable(),
            'extra' => [],
        ];

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
