<?php declare(strict_types=1);

namespace Shopware\Core\Migration\Traits;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * Use in migrations that issue an `ALTER TABLE` on a parent table whose
 * children may carry non-standard FKs (FKs referencing a non-PK / non-unique
 * column in the parent). MySQL 8.4 introduced `restrict_fk_on_non_standard_key`
 * (default ON); MySQL bug #118151 makes such ALTERs fail with a misleading
 * `Cannot drop index '<unknown key name>': needed in a foreign key constraint`
 * even when the DDL is unrelated to foreign keys.
 *
 * `runWithRelaxedNonStandardFkGuard()` relaxes the guard for the current
 * session only (no global state change, no admin privileges required),
 * executes the callback, and restores the previous value. On MariaDB and
 * MySQL <8.4 the variable does not exist and the callback runs unchanged.
 */
#[Package('framework')]
trait RelaxesNonStandardFkGuardTrait
{
    /**
     * @param callable(Connection): void $callback
     */
    protected function runWithRelaxedNonStandardFkGuard(Connection $connection, callable $callback): void
    {
        $previousGuard = null;
        try {
            $previousGuard = (int) $connection->fetchOne('SELECT @@SESSION.restrict_fk_on_non_standard_key');
            $connection->executeStatement('SET SESSION restrict_fk_on_non_standard_key = OFF');
        } catch (\Throwable) {
        }

        try {
            $callback($connection);
        } finally {
            if ($previousGuard !== null) {
                $connection->executeStatement(\sprintf(
                    'SET SESSION restrict_fk_on_non_standard_key = %d',
                    $previousGuard
                ));
            }
        }
    }
}
