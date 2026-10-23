<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Doctrine;

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
                return $exception;
            } elseif (
                $missingSavepointException === null
                && self::isMissingSavepoint($exception)
            ) {
                // The missing savepoint can mask the exception which caused MariaDB to roll back the transaction.
                // Keep it only as a fallback for https://github.com/doctrine/dbal/issues/6651.
                $missingSavepointException = $exception;
            }

            $exception = $exception->getPrevious();
        } while ($exception !== null);

        return $missingSavepointException;
    }

    public static function unwrap(\Throwable $exception): \Throwable
    {
        // Preserve application wrappers and their diagnostics. Only rollback failures mask the original error.
        return self::isMissingSavepoint($exception) ? (self::detect($exception) ?? $exception) : $exception;
    }

    private static function isMissingSavepoint(\Throwable $exception): bool
    {
        return $exception instanceof DriverException
            && preg_match('/SAVEPOINT [^\s]+ does not exist/', $exception->getMessage()) === 1;
    }
}
