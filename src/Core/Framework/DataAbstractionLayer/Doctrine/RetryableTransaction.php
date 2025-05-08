<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Doctrine;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\DeadlockException;
use Doctrine\DBAL\Exception\DriverException;
use Doctrine\DBAL\Exception\LockWaitTimeoutException;
use Doctrine\DBAL\Exception\RetryableException;
use Doctrine\DBAL\Exception\TransactionRolledBack;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Telemetry\Metrics\MeterProvider;
use Shopware\Core\Framework\Telemetry\Metrics\Metric\ConfiguredMetric;

#[Package('framework')]
class RetryableTransaction
{
    /**
     * Executes the given closure inside a DBAL transaction. In case of a deadlock (RetryableException) the transaction
     * is rolled back and the closure will be retried. Because it may run multiple times the closure should not cause
     * any side effects outside its own scope.
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
                // when any RetryableException is thrown.
                // Rethrow the exception here so only the outermost transaction is retried which in turn includes this
                // nested transaction.
                throw $e;
            }

            // after failure and rollback in transactional we need to make sure the nesting level
            // is correct (see https://github.com/doctrine/dbal/issues/6651) and transaction is rolled back
            // it's safe to assume that correct nesting level is 0, as we check for transaction nesting level
            // in condition above
            self::fixConnection($connection);

            $deadlockRelatedException = self::deadlockRelatedException($e);

            if ($deadlockRelatedException) {
                MeterProvider::meter()?->emit(new ConfiguredMetric('database.locks.count', 1));
            }

            if ($counter > 10 || !$deadlockRelatedException) {
                throw $e;
            }

            // Randomize sleep to prevent same execution delay for multiple statements
            usleep(random_int(10, 20));

            return self::retry($connection, $closure, $counter, $transactionNestingLevel);
        }
    }

    private static function deadlockRelatedException(\Throwable $e): bool
    {
        return
            $e instanceof TransactionRolledBack
            || $e instanceof DeadlockException
            || $e instanceof LockWaitTimeoutException
            // caused by the https://github.com/doctrine/dbal/issues/6651
            || ($e instanceof DriverException && preg_match('/SAVEPOINT [^\s]+ does not exist/', $e->getMessage()))
        ;
    }

    private static function fixConnection(Connection $connection): void
    {
        if ($connection->getTransactionNestingLevel() > 0) {
            $reflectionProperty = new \ReflectionProperty(Connection::class, 'transactionNestingLevel');
            $reflectionProperty->setAccessible(true);
            $reflectionProperty->setValue($connection, 1);
            // it could happen that transaction was already rolled back in the transactional method.
            // if case reported - need to catch specific exception
            $connection->rollBack();
        }
    }
}
