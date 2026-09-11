<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Doctrine;

use Doctrine\DBAL\Driver\Exception as DriverExceptionInterface;
use Doctrine\DBAL\Exception as DbalException;
use Doctrine\DBAL\Exception\DriverException;
use Doctrine\DBAL\Exception\RetryableException;
use Doctrine\DBAL\Exception\TransactionRolledBack;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
class RetryableExceptionDetector
{
    private const MARIADB_RECORD_CHANGED_ERROR_CODE = 1020;

    public static function detect(\Throwable $exception): ?\Throwable
    {
        if (!$exception instanceof DbalException) {
            return null;
        }

        $retryableException = null;
        $missingSavepointException = null;

        do {
            if (
                $exception instanceof DbalException
                && (
                    $exception instanceof RetryableException
                    || $exception instanceof TransactionRolledBack
                    || ($exception instanceof DriverException && $exception->getCode() === self::MARIADB_RECORD_CHANGED_ERROR_CODE)
                )
            ) {
                $retryableException = $exception;
            } elseif (
                $missingSavepointException === null
                && $exception instanceof DriverException
                && preg_match('/SAVEPOINT [^\s]+ does not exist/', $exception->getMessage())
            ) {
                // The missing savepoint can mask the exception which caused MariaDB to roll back the transaction.
                // Keep it only as a fallback for https://github.com/doctrine/dbal/issues/6651.
                $missingSavepointException = $exception;
            }

            $exception = $exception->getPrevious();
        } while (
            $exception instanceof DbalException
            || $exception instanceof DriverExceptionInterface
            || $exception instanceof \PDOException
        );

        return $retryableException ?? $missingSavepointException;
    }
}
