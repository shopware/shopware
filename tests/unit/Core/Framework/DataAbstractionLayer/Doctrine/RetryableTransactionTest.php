<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\Doctrine;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\PDO\Exception;
use Doctrine\DBAL\Exception\DeadlockException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\RetryableTransaction;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(RetryableTransaction::class)]
class RetryableTransactionTest extends TestCase
{
    public function testRetryableTransactionRetriesOnDeadlock(): void
    {
        $this->expectException(DeadlockException::class);

        $counter = 0;
        $f = static function () use (&$counter): void {
            ++$counter;
            throw new DeadlockException(
                new Exception('Deadlock detected'),
                null,
            );
        };

        $connection = static::createStub(Connection::class);
        $connection->method('getTransactionNestingLevel')->willReturn(0);
        $connection->method('transactional')->willReturnCallback($f);

        try {
            RetryableTransaction::retryable($connection, $f);
        } finally {
            static::assertSame(11, $counter);
        }
    }

    public function testReadCommittedIsAppliedBeforeTheTransaction(): void
    {
        $statements = [];

        $connection = static::createStub(Connection::class);
        $connection->method('getTransactionNestingLevel')->willReturn(0);
        $connection->method('fetchNumeric')->willReturn(['1', 'MIXED']);
        $connection->method('executeStatement')->willReturnCallback(function (string $sql) use (&$statements): int {
            $statements[] = $sql;

            return 0;
        });
        $connection->method('transactional')->willReturnCallback(static fn (\Closure $closure) => $closure());

        $result = RetryableTransaction::retryableReadCommitted($connection, static fn () => 'done');

        static::assertSame('done', $result);
        static::assertSame(['SET TRANSACTION ISOLATION LEVEL READ COMMITTED'], $statements);
    }

    public function testReadCommittedIsReappliedOnEveryRetry(): void
    {
        $this->expectException(DeadlockException::class);

        $statements = [];
        $counter = 0;

        $connection = static::createStub(Connection::class);
        $connection->method('getTransactionNestingLevel')->willReturn(0);
        $connection->method('fetchNumeric')->willReturn(['1', 'ROW']);
        $connection->method('executeStatement')->willReturnCallback(function (string $sql) use (&$statements): int {
            $statements[] = $sql;

            return 0;
        });
        $connection->method('transactional')->willReturnCallback(static function () use (&$counter): void {
            ++$counter;
            throw new DeadlockException(new Exception('Deadlock detected'), null);
        });

        try {
            RetryableTransaction::retryableReadCommitted($connection, static fn () => null);
        } finally {
            static::assertSame(11, $counter);
            static::assertCount(11, $statements, 'the isolation level applies to a single transaction and must be set again before every retry');
        }
    }

    public function testReadCommittedIsSkippedForStatementBasedBinlog(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('getTransactionNestingLevel')->willReturn(0);
        $connection->method('fetchNumeric')->willReturn(['1', 'STATEMENT']);
        $connection->expects($this->never())->method('executeStatement');
        $connection->method('transactional')->willReturnCallback(static fn (\Closure $closure) => $closure());

        $result = RetryableTransaction::retryableReadCommitted($connection, static fn () => 'done');

        static::assertSame('done', $result);
    }

    public function testReadCommittedIsSkippedInsideAnOuterTransaction(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('getTransactionNestingLevel')->willReturn(1);
        $connection->method('fetchNumeric')->willReturn(['1', 'MIXED']);
        $connection->expects($this->never())->method('executeStatement');
        $connection->method('transactional')->willReturnCallback(static fn (\Closure $closure) => $closure());

        $result = RetryableTransaction::retryableReadCommitted($connection, static fn () => 'done');

        static::assertSame('done', $result);
    }

    public function testReadCommittedIsSkippedWhenTheBinlogStateIsUnknown(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('getTransactionNestingLevel')->willReturn(0);
        $connection->method('fetchNumeric')->willReturn(false);
        $connection->expects($this->never())->method('executeStatement');
        $connection->method('transactional')->willReturnCallback(static fn (\Closure $closure) => $closure());

        $result = RetryableTransaction::retryableReadCommitted($connection, static fn () => 'done');

        static::assertSame('done', $result);
    }
}
