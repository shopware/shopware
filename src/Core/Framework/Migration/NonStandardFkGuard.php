<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Migration;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\DriverException;
use Shopware\Core\Framework\Log\Package;

/**
 * Executes DDL statements, retrying with `restrict_fk_on_non_standard_key` relaxed when the
 * statement trips MySQL bug #118151.
 *
 * MySQL 8.4 enables that guard by default, and while it is on, the bug makes any `ALTER TABLE` /
 * `CREATE INDEX` on a table fail with `Cannot drop index '<unknown key name>': needed in a
 * foreign key constraint` (error 1553) when the table is involved in a foreign key with a
 * non-standard supporting key. Shops carrying such drift cannot upgrade without this.
 *
 * The statement always runs unmodified first. Only when it failed with error 1553 while the
 * guard is ON is the guard relaxed for a single retry, restoring the previous value afterwards.
 * A legitimate 1553 failure fails the retry as well and surfaces as usual, so behaviour only
 * changes for statements the bug would otherwise reject. On MariaDB and MySQL < 8.4 the
 * variable does not exist and no retry happens.
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
