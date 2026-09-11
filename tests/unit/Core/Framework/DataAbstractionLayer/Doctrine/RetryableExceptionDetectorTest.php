<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\Doctrine;

use Doctrine\DBAL\Driver\PDO\Exception as DriverException;
use Doctrine\DBAL\Exception\DeadlockException;
use Doctrine\DBAL\Exception\DriverException as DbalDriverException;
use Doctrine\DBAL\Exception\TransactionRolledBack;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
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

    public function testDoesNotInspectExceptionChainOfApplicationException(): void
    {
        $deadlockException = new DeadlockException(new DriverException('Deadlock detected'), null);
        $exception = new \RuntimeException('Application error', previous: $deadlockException);

        static::assertNull(RetryableExceptionDetector::detect($exception));
    }

    public function testStopsAtApplicationExceptionInsideDbalChain(): void
    {
        $deadlockException = new DeadlockException(new DriverException('Deadlock detected'), null);
        $applicationException = new \RuntimeException('Application error', previous: $deadlockException);
        $exception = self::createDriverException(1062, 'Duplicate entry', $applicationException);

        static::assertNull(RetryableExceptionDetector::detect($exception));
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
