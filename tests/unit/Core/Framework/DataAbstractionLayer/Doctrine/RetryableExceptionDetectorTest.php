<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\Doctrine;

use Doctrine\DBAL\Driver\PDO\Exception as DriverException;
use Doctrine\DBAL\Exception\DeadlockException;
use Doctrine\DBAL\Exception\DriverException as DbalDriverException;
use Doctrine\DBAL\Exception\TransactionRolledBack;
use Doctrine\DBAL\Query;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Flow\FlowException;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\RetryableExceptionDetector;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(RetryableExceptionDetector::class)]
class RetryableExceptionDetectorTest extends TestCase
{
    public function testDetectsDoctrineRetryableException(): void
    {
        $exception = new DeadlockException(new DriverException('Deadlock detected'), null);

        static::assertSame($exception, RetryableExceptionDetector::detect($exception));
    }

    public function testDetectsTransactionRolledBackException(): void
    {
        $exception = new TransactionRolledBack(new DriverException('Transaction rolled back'), null);

        static::assertSame($exception, RetryableExceptionDetector::detect($exception));
    }

    public function testDetectsMariaDbRecordChangedException(): void
    {
        $exception = self::createDriverException(1020, 'Record has changed since last read');

        static::assertSame($exception, RetryableExceptionDetector::detect($exception));
    }

    public function testReturnsUnderlyingMariaDbExceptionFromSavepointWrappers(): void
    {
        $underlyingException = self::createDriverException(1020, 'Record has changed since last read');
        $innerSavepointException = self::createDriverException(
            1305,
            'SAVEPOINT DOCTRINE_2 does not exist',
            $underlyingException,
        );
        $outerSavepointException = self::createDriverException(
            1305,
            'SAVEPOINT DOCTRINE_2 does not exist',
            $innerSavepointException,
        );

        static::assertSame($underlyingException, RetryableExceptionDetector::detect($outerSavepointException));
    }

    public function testDetectsMissingSavepointAsFallback(): void
    {
        $exception = self::createDriverException(1305, 'SAVEPOINT DOCTRINE_2 does not exist');

        static::assertSame($exception, RetryableExceptionDetector::detect($exception));
    }

    public function testDoesNotDetectUnrelatedException(): void
    {
        $exception = self::createDriverException(1062, 'Duplicate entry');

        static::assertNull(RetryableExceptionDetector::detect($exception));
    }

    public function testDoesNotDetectNonDbalExceptionWithMariaDbErrorCode(): void
    {
        $exception = new \RuntimeException('Application error', 1020);

        static::assertNull(RetryableExceptionDetector::detect($exception));
    }

    public function testDetectsContentionWrappedInAFlowException(): void
    {
        $deadlockException = new DeadlockException(new DriverException('Deadlock detected'), null);
        $exception = FlowException::transactionFailed($deadlockException);

        static::assertSame($deadlockException, RetryableExceptionDetector::detect($exception));
        static::assertSame($exception, RetryableExceptionDetector::unwrap($exception));
    }

    public function testDetectsContentionThroughApplicationAndSavepointWrappers(): void
    {
        $deadlockException = new DeadlockException(new DriverException('Deadlock detected'), null);
        $applicationException = new \RuntimeException('Application error', previous: $deadlockException);
        $exception = self::createDriverException(1305, 'SAVEPOINT DOCTRINE_2 does not exist', $applicationException);

        static::assertSame($deadlockException, RetryableExceptionDetector::detect($exception));
        static::assertSame($deadlockException, RetryableExceptionDetector::unwrap($exception));
    }

    public function testUnwrappingPreservesTheOriginalFailedQuery(): void
    {
        $query = new Query('UPDATE product SET available = ? WHERE id = ?', [false, 'product-id'], []);
        $original = new DbalDriverException(new DriverException('Record has changed since last read', 'HY000', 1020), $query);
        $wrapper = new DbalDriverException(
            new DriverException('SAVEPOINT DOCTRINE_2 does not exist', '42000', 1305, $original),
            new Query('ROLLBACK TO SAVEPOINT DOCTRINE_2', [], []),
        );

        static::assertSame($original, RetryableExceptionDetector::unwrap($wrapper));
        static::assertSame($query, $original->getQuery());
        static::assertSame($original, RetryableExceptionDetector::unwrap($original));
    }

    public function testKeepsTheFirstContentionWhenCleanupAlsoFails(): void
    {
        $cleanup = new DeadlockException(new DriverException('Cleanup deadlock'), null);
        $original = self::createDriverException(1020, 'Original record-change conflict', $cleanup);

        static::assertSame($original, RetryableExceptionDetector::detect($original));
        static::assertSame($original, RetryableExceptionDetector::unwrap($original));
    }

    public function testWrappedUnrelatedDatabaseErrorIsNotRetryable(): void
    {
        $exception = FlowException::transactionFailed(self::createDriverException(1062, 'Duplicate entry'));

        static::assertNull(RetryableExceptionDetector::detect($exception));
        static::assertSame($exception, RetryableExceptionDetector::unwrap($exception));
    }

    public function testUnwrappingMissingSavepointWithoutACauseKeepsIt(): void
    {
        $exception = self::createDriverException(1305, 'SAVEPOINT DOCTRINE_2 does not exist');

        static::assertSame($exception, RetryableExceptionDetector::unwrap($exception));
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
