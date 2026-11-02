<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\Doctrine;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\PDO\Exception as PdoException;
use Doctrine\DBAL\Exception\DriverException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\RetryableWriteTransaction;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(RetryableWriteTransaction::class)]
class RetryableWriteTransactionTest extends TestCase
{
    public function testRetriesTheWholeNestedWriteBeforeCallbacksStart(): void
    {
        $connection = $this->createConnection();
        $attempts = 0;

        $result = RetryableWriteTransaction::retryable($connection, static function () use ($connection, &$attempts): string {
            return RetryableWriteTransaction::retryable($connection, static function () use (&$attempts): string {
                if (++$attempts === 1) {
                    throw self::recordChangedException();
                }

                return 'written';
            });
        });

        static::assertSame('written', $result);
        static::assertSame(2, $attempts);
        static::assertSame(0, $connection->getTransactionNestingLevel());
    }

    public function testNestedCallbacksPreventTheOuterWriteFromRetrying(): void
    {
        $connection = $this->createConnection();
        $failure = self::recordChangedException();
        $attempts = 0;

        $this->expectExceptionObject($failure);

        try {
            RetryableWriteTransaction::retryable($connection, static function () use ($connection, $failure, &$attempts): never {
                ++$attempts;
                RetryableWriteTransaction::retryable($connection, static function () use ($connection): void {
                    RetryableWriteTransaction::preventRetries($connection);
                });

                throw $failure;
            });
        } finally {
            static::assertSame(1, $attempts);
            static::assertSame(0, $connection->getTransactionNestingLevel());
        }
    }

    public function testLaterNestedScopesCannotClearAnEarlierCallbackMarker(): void
    {
        $connection = $this->createConnection();
        $failure = self::recordChangedException();
        $attempts = 0;

        $this->expectExceptionObject($failure);

        try {
            RetryableWriteTransaction::retryable($connection, static function () use ($connection, $failure, &$attempts): never {
                ++$attempts;
                RetryableWriteTransaction::preventRetries($connection);
                RetryableWriteTransaction::retryable($connection, static function () use ($failure): never {
                    throw $failure;
                });
            });
        } finally {
            static::assertSame(1, $attempts);
        }
    }

    public function testPreventionIsIsolatedToItsConnection(): void
    {
        $connection = $this->createConnection();
        $otherConnection = $this->createConnection();
        $attempts = 0;

        RetryableWriteTransaction::retryable($connection, static function () use ($connection, $otherConnection, &$attempts): void {
            RetryableWriteTransaction::preventRetries($connection);
            RetryableWriteTransaction::retryable($otherConnection, static function () use (&$attempts): void {
                if (++$attempts === 1) {
                    throw self::recordChangedException();
                }
            });
        });

        static::assertSame(2, $attempts);
    }

    public function testCallbacksOnARetriedAttemptPreventFurtherRetries(): void
    {
        $connection = $this->createConnection();
        $failure = self::recordChangedException();
        $attempts = 0;
        $this->expectExceptionObject($failure);

        try {
            RetryableWriteTransaction::retryable($connection, static function () use ($connection, $failure, &$attempts): never {
                if (++$attempts === 2) {
                    RetryableWriteTransaction::preventRetries($connection);
                }

                throw $failure;
            });
        } finally {
            static::assertSame(2, $attempts);
        }
    }

    public function testSuccessfulScopeDoesNotDisableTheNextWrite(): void
    {
        $connection = $this->createConnection();
        RetryableWriteTransaction::retryable($connection, static function () use ($connection): void {
            RetryableWriteTransaction::preventRetries($connection);
        });

        $attempts = 0;
        RetryableWriteTransaction::retryable($connection, static function () use (&$attempts): void {
            if (++$attempts === 1) {
                throw self::recordChangedException();
            }
        });

        static::assertSame(2, $attempts);
    }

    public function testFailedScopeDoesNotDisableTheNextWrite(): void
    {
        $connection = $this->createConnection();
        $failure = self::recordChangedException();
        $this->expectExceptionObject($failure);

        try {
            RetryableWriteTransaction::retryable($connection, static function () use ($connection, $failure): never {
                RetryableWriteTransaction::preventRetries($connection);

                throw $failure;
            });
        } finally {
            $attempts = 0;
            RetryableWriteTransaction::retryable($connection, static function () use (&$attempts): void {
                if (++$attempts === 1) {
                    throw self::recordChangedException();
                }
            });

            static::assertSame(2, $attempts);
        }
    }

    public function testDoesNotRetryInsideAnUnmanagedOuterTransaction(): void
    {
        $connection = $this->createConnection(transactionNestingLevel: 1);
        $failure = self::recordChangedException();
        $attempts = 0;
        $this->expectExceptionObject($failure);

        try {
            RetryableWriteTransaction::retryable($connection, static function () use ($failure, &$attempts): never {
                ++$attempts;

                throw $failure;
            });
        } finally {
            static::assertSame(1, $attempts);
            static::assertSame(1, $connection->getTransactionNestingLevel());
        }
    }

    public function testPreventionOutsideAScopeDoesNotDisableFutureWrites(): void
    {
        $connection = $this->createConnection();
        RetryableWriteTransaction::preventRetries($connection);
        $attempts = 0;

        RetryableWriteTransaction::retryable($connection, static function () use (&$attempts): void {
            if (++$attempts === 1) {
                throw self::recordChangedException();
            }
        });

        static::assertSame(2, $attempts);
    }

    private function createConnection(int $transactionNestingLevel = 0): Connection
    {
        $connection = static::createStub(Connection::class);
        $connection->method('getTransactionNestingLevel')->willReturnCallback(static function () use (&$transactionNestingLevel): int {
            return $transactionNestingLevel;
        });
        $connection->method('transactional')->willReturnCallback(static function (\Closure $closure) use ($connection, &$transactionNestingLevel): mixed {
            ++$transactionNestingLevel;

            try {
                return $closure($connection);
            } finally {
                --$transactionNestingLevel;
            }
        });

        return $connection;
    }

    private static function recordChangedException(): DriverException
    {
        return new DriverException(new PdoException('Record has changed since last read', 'HY000', 1020), null);
    }
}
