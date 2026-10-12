<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Migration;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\DriverException;
use Shopware\Core\Framework\Log\Package;

/**
 * Executes DDL, retrying once with `restrict_fk_on_non_standard_key` relaxed when MySQL 8.4
 * rejects the statement with error 1553 through bug #118151. Legitimate 1553 failures fail the
 * retry as well; without the variable (MariaDB, MySQL < 8.4) there is no retry.
 *
 * @see https://bugs.mysql.com/bug.php?id=118151
 *
 * @internal Temporary workaround, will be removed once MySQL fixes bug #118151
 */
#[Package('framework')]
final class NonStandardFkGuard
{
    private const GUARD_VARIABLE = 'restrict_fk_on_non_standard_key';

    private const ER_DROP_INDEX_FK = 1553;

    public static function executeDdl(Connection $connection, string $sql): void
    {
        try {
            $connection->executeStatement($sql);
        } catch (DriverException $e) {
            if ($e->getCode() !== self::ER_DROP_INDEX_FK) {
                throw $e;
            }

            self::retryWithRelaxedGuard($connection, $sql, $e);
        }
    }

    private static function retryWithRelaxedGuard(Connection $connection, string $sql, DriverException $original): void
    {
        // Empty result means the server does not know the variable. More reliable than parsing
        // VERSION(), which reports strings like "5.5.5-10.11.2-MariaDB" that compare as >= 8.4.
        $guard = $connection->fetchAssociative(
            'SHOW SESSION VARIABLES LIKE :variable',
            ['variable' => self::GUARD_VARIABLE]
        );

        // Variable absent (MariaDB, MySQL < 8.4) or already relaxed: the guard did not cause the
        // failure, so a retry cannot change the outcome.
        if ($guard === false || $guard['Value'] !== 'ON') {
            throw $original;
        }

        $connection->executeStatement('SET SESSION ' . self::GUARD_VARIABLE . ' = OFF');

        try {
            $connection->executeStatement($sql);
        } finally {
            $connection->executeStatement('SET SESSION ' . self::GUARD_VARIABLE . ' = ON');
        }
    }
}
