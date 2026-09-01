<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\Doctrine;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\PDO\Exception as DriverException;
use Doctrine\DBAL\Exception\DeadlockException;
use Doctrine\DBAL\Exception\DriverException as DbalDriverException;
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
                new DriverException('Deadlock detected'),
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

    public function testRetryableTransactionRetriesMariaDbRecordChangedException(): void
    {
        $exception = self::createDriverException(1020, 'Record has changed since last read');
        $counter = 0;
        $connection = $this->createTransactionConnectionStub();

        $result = RetryableTransaction::retryable(
            $connection,
            static function () use (&$counter, $exception): string {
                ++$counter;

                if ($counter === 1) {
                    throw $exception;
                }

                return 'success';
            },
        );

        static::assertSame('success', $result);
        static::assertSame(2, $counter);
    }

    public function testRetryableTransactionThrowsUnderlyingExceptionAfterExhaustion(): void
    {
        $underlyingException = self::createDriverException(1020, 'Record has changed since last read');
        $savepointException = self::createDriverException(
            1305,
            'SAVEPOINT DOCTRINE_2 does not exist',
            $underlyingException,
        );
        $counter = 0;
        $connection = $this->createTransactionConnectionStub();

        $this->expectExceptionObject($underlyingException);

        try {
            RetryableTransaction::retryable(
                $connection,
                static function () use (&$counter, $savepointException): never {
                    ++$counter;

                    throw $savepointException;
                },
            );
        } finally {
            static::assertSame(11, $counter);
        }
    }

    public function testNestedRetryableTransactionDoesNotRetryOrUnwrapException(): void
    {
        $underlyingException = self::createDriverException(1020, 'Record has changed since last read');
        $savepointException = self::createDriverException(
            1305,
            'SAVEPOINT DOCTRINE_2 does not exist',
            $underlyingException,
        );
        $counter = 0;
        $connection = $this->createTransactionConnectionStub(1);

        $this->expectExceptionObject($savepointException);

        try {
            RetryableTransaction::retryable(
                $connection,
                static function () use (&$counter, $savepointException): never {
                    ++$counter;

                    throw $savepointException;
                },
            );
        } finally {
            static::assertSame(1, $counter);
        }
    }

    public function testTransactionalThrowsUnderlyingExceptionMaskedByMissingSavepoint(): void
    {
        $underlyingException = self::createDriverException(1020, 'Record has changed since last read');
        $savepointException = self::createDriverException(
            1305,
            'SAVEPOINT DOCTRINE_2 does not exist',
            $underlyingException,
        );
        $connection = $this->createTransactionConnectionStub();

        $this->expectExceptionObject($underlyingException);

        RetryableTransaction::transactional(
            $connection,
            static function () use ($savepointException): never {
                throw $savepointException;
            },
        );
    }

    public function testNestedTransactionalDoesNotUnwrapException(): void
    {
        $underlyingException = self::createDriverException(1020, 'Record has changed since last read');
        $savepointException = self::createDriverException(
            1305,
            'SAVEPOINT DOCTRINE_2 does not exist',
            $underlyingException,
        );
        $connection = $this->createTransactionConnectionStub(1);

        $this->expectExceptionObject($savepointException);

        RetryableTransaction::transactional(
            $connection,
            static function () use ($savepointException): never {
                throw $savepointException;
            },
        );
    }

    public function testRetryableTransactionDoesNotRetryUnrelatedException(): void
    {
        $exception = self::createDriverException(1062, 'Duplicate entry');
        $counter = 0;
        $connection = $this->createTransactionConnectionStub();

        $this->expectExceptionObject($exception);

        try {
            RetryableTransaction::retryable(
                $connection,
                static function () use (&$counter, $exception): never {
                    ++$counter;

                    throw $exception;
                },
            );
        } finally {
            static::assertSame(1, $counter);
        }
    }

    private function createTransactionConnectionStub(int $transactionNestingLevel = 0): Connection
    {
        $connection = static::createStub(Connection::class);
        $connection->method('getTransactionNestingLevel')->willReturn($transactionNestingLevel);
        $connection->method('transactional')->willReturnCallback(
            static fn (\Closure $closure): mixed => $closure($connection),
        );

        return $connection;
    }

    private static function createDriverException(
        int $code,
        string $message,
        ?\Throwable $previous = null,
    ): DbalDriverException {
        return new DbalDriverException(
            DriverException::new(new \PDOException($message, $code, $previous)),
            null,
        );
    }
}
