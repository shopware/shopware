<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\Doctrine;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\PDO\Exception as PdoException;
use Doctrine\DBAL\Exception\DriverException;
use Doctrine\DBAL\Statement;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\RetryableQuery;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(RetryableQuery::class)]
class RetryableQueryTest extends TestCase
{
    public function testPreparedStatementRetriesMariaDbRecordChangedException(): void
    {
        $exception = self::createDriverException(1020, 'Record has changed since last read');
        $attempts = 0;

        $connection = static::createStub(Connection::class);
        $connection->method('getTransactionNestingLevel')->willReturn(0);

        $statement = $this->createMock(Statement::class);
        $statement->expects($this->exactly(2))
            ->method('bindValue')
            ->with('id', 'product-id');
        $statement->expects($this->exactly(2))
            ->method('executeStatement')
            ->willReturnCallback(static function () use (&$attempts, $exception): int {
                ++$attempts;

                if ($attempts === 1) {
                    throw $exception;
                }

                return 1;
            });

        $query = new RetryableQuery($connection, $statement);

        static::assertSame(1, $query->execute(['id' => 'product-id']));
        static::assertSame(2, $attempts);
    }

    public function testRetriesMariaDbRecordChangedException(): void
    {
        $exception = self::createDriverException(1020, 'Record has changed since last read');
        $attempts = 0;

        $connection = static::createStub(Connection::class);
        $connection->method('getTransactionNestingLevel')->willReturn(0);

        $result = RetryableQuery::retryable($connection, static function () use (&$attempts, $exception): string {
            ++$attempts;

            if ($attempts === 1) {
                throw $exception;
            }

            return 'success';
        });

        static::assertSame('success', $result);
        static::assertSame(2, $attempts);
    }

    public function testDoesNotRetryInsideTransaction(): void
    {
        $exception = self::createDriverException(1020, 'Record has changed since last read');
        $attempts = 0;

        $connection = static::createStub(Connection::class);
        $connection->method('getTransactionNestingLevel')->willReturn(1);

        $this->expectExceptionObject($exception);

        try {
            RetryableQuery::retryable($connection, static function () use (&$attempts, $exception): void {
                ++$attempts;

                throw $exception;
            });
        } finally {
            static::assertSame(1, $attempts);
        }
    }

    public function testDoesNotRetryUnrelatedDriverException(): void
    {
        $exception = self::createDriverException(1064, 'Syntax error');
        $attempts = 0;

        $connection = static::createStub(Connection::class);
        $connection->method('getTransactionNestingLevel')->willReturn(0);

        $this->expectExceptionObject($exception);

        try {
            RetryableQuery::retryable($connection, static function () use (&$attempts, $exception): void {
                ++$attempts;

                throw $exception;
            });
        } finally {
            static::assertSame(1, $attempts);
        }
    }

    public function testThrowsUnderlyingRecordChangedExceptionAfterRetriesAreExhausted(): void
    {
        $recordChangedException = self::createDriverException(1020, 'Record has changed since last read');
        $exception = self::createDriverException(
            1305,
            'SAVEPOINT DOCTRINE_2 does not exist',
            self::createDriverException(1305, 'SAVEPOINT DOCTRINE_2 does not exist', $recordChangedException),
        );
        $attempts = 0;

        $connection = static::createStub(Connection::class);
        $connection->method('getTransactionNestingLevel')->willReturn(0);

        $this->expectExceptionObject($recordChangedException);

        try {
            RetryableQuery::retryable($connection, static function () use (&$attempts, $exception): void {
                ++$attempts;

                throw $exception;
            });
        } finally {
            static::assertSame(11, $attempts);
        }
    }

    private static function createDriverException(
        int $errorCode,
        string $message,
        ?\Throwable $previous = null,
    ): DriverException {
        return new DriverException(
            new PdoException($message, 'HY000', $errorCode, $previous),
            null,
        );
    }
}
