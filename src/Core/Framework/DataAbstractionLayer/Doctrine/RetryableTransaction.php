<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Doctrine;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Telemetry\Metrics\MeterProvider;
use Shopware\Core\Framework\Telemetry\Metrics\Metric\ConfiguredMetric;

#[Package('framework')]
class RetryableTransaction
{
    /**
     * Executes the given closure inside a DBAL transaction. In case of a retryable database error the transaction is
     * rolled back and the closure will be retried. Because it may run multiple times the closure should not cause any
     * side effects outside its own scope.
     *
     * @template TReturn of mixed
     *
     * @param \Closure(Connection): TReturn $closure
     *
     * @return TReturn
     */
    public static function retryable(Connection $connection, \Closure $closure)
    {
        return self::retry($connection, $closure, 0, $connection->getTransactionNestingLevel());
    }

    /**
     * Executes the given closure inside a DBAL transaction. In case of a retryable database error the transaction is
     * rolled back. There are no retries. If a missing-savepoint exception masks the retryable database error, the
     * underlying exception is re-thrown.
     *
     * @template TReturn of mixed
     *
     * @param \Closure(Connection): TReturn $closure
     *
     * @return TReturn
     */
    public static function transactional(Connection $connection, \Closure $closure)
    {
        $originalNestingLevel = $connection->getTransactionNestingLevel();
        try {
            return $connection->transactional($closure);
        } catch (\Throwable $e) {
            if ($originalNestingLevel > 0) {
                // If this RetryableTransaction was executed inside another transaction, do not retry this nested
                // transaction. Remember that the whole (outermost) transaction was already rolled back by the database
                // when any retryable database exception is thrown.
                // Rethrow the exception here so only the outermost transaction is retried which in turn includes this
                // nested transaction.
                throw $e;
            }

            // after failure and rollback in transactional we need to make sure the nesting level
            // is correct (see https://github.com/doctrine/dbal/issues/6651) and transaction is rolled back
            // it's safe to assume that correct nesting level is 0, as we check for transaction nesting level
            // in condition above
            self::fixConnection($connection);

            // The transactionNestingLevel is fixed, so this won't cause follow-up issues. A missing-savepoint
            // exception can mask the database error which caused the rollback, so expose that underlying error.
            throw RetryableExceptionDetector::detect($e) ?? $e;
        }
    }

    /**
     * @template TReturn of mixed
     *
     * @param \Closure(Connection): TReturn $closure The function to execute transactionally.
     *
     * @return TReturn
     */
    private static function retry(Connection $connection, \Closure $closure, int $counter, int $transactionNestingLevel)
    {
        ++$counter;
        try {
            return $connection->transactional($closure);
        } catch (\Throwable $e) {
            if ($transactionNestingLevel > 0) {
                // If this RetryableTransaction was executed inside another transaction, do not retry this nested
                // transaction. Remember that the whole (outermost) transaction was already rolled back by the database
                // when any retryable database exception is thrown.
                // Rethrow the exception here so only the outermost transaction is retried which in turn includes this
                // nested transaction.
                throw $e;
            }

            // after failure and rollback in transactional we need to make sure the nesting level
            // is correct (see https://github.com/doctrine/dbal/issues/6651) and transaction is rolled back
            // it's safe to assume that correct nesting level is 0, as we check for transaction nesting level
            // in condition above
            self::fixConnection($connection);

            $retryableException = RetryableExceptionDetector::detect($e);

            if ($retryableException) {
                MeterProvider::meter()?->emit(new ConfiguredMetric('database.locks.count', 1));
            }

            if ($counter > 10 || !$retryableException) {
                throw $retryableException ?? $e;
            }

            // Randomize sleep to prevent same execution delay for multiple statements
            usleep(random_int(10, 20));

            return self::retry($connection, $closure, $counter, $transactionNestingLevel);
        }
    }

    private static function fixConnection(Connection $connection): void
    {
        if ($connection->getTransactionNestingLevel() > 0) {
            $reflectionProperty = new \ReflectionProperty(Connection::class, 'transactionNestingLevel');
            $reflectionProperty->setValue($connection, 1);
            // it could happen that transaction was already rolled back in the transactional method.
            // if case reported - need to catch specific exception
            $connection->rollBack();
        }
    }
}
