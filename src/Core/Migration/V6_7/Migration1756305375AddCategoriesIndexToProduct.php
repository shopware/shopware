<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\DriverException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationException;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Util\Database\TableHelper;

/**
 * @internal
 */
#[Package('inventory')]
class Migration1756305375AddCategoriesIndexToProduct extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1756305375;
    }

    public function update(Connection $connection): void
    {
        if (TableHelper::indexExists($connection, 'product', 'idx.product.categories')) {
            return;
        }

        try {
            $connection->executeStatement('CREATE INDEX `idx.product.categories` ON `product` (`categories`)');
        } catch (DriverException $e) {
            // MySQL 8.4+ ships with `restrict_fk_on_non_standard_key` enabled by default.
            // Combined with the pre-existing non-standard FK supporting indexes on `product`
            // this triggers upstream MySQL bug https://bugs.mysql.com/bug.php?id=118151,
            // which rejects ANY index DDL on the table with
            // "Cannot drop index '<unknown key name>': needed in a foreign key constraint"
            // (SQLSTATE HY000, error 1553) – even though nothing is being dropped.
            //
            // See https://github.com/shopware/shopware/issues/13039.
            //
            // Re-throw with a clear, actionable message so the operator knows what to do.
            if ($this->isMysqlNonStandardKeyRestriction($e, $connection)) {
                throw MigrationException::migrationError(
                    'Unable to create index `idx.product.categories` on table `product`. '
                    . 'Your MySQL server rejected the statement with error 1553 ("Cannot drop index ... '
                    . 'needed in a foreign key constraint"). This is caused by upstream MySQL bug #118151, '
                    . 'triggered on MySQL 8.4+ when the server variable `restrict_fk_on_non_standard_key` is '
                    . 'ON (the default on MySQL 8.4 and later). '
                    . 'Workaround: set `restrict_fk_on_non_standard_key = OFF` in your MySQL configuration '
                    . '(my.cnf under [mysqld], or `SET GLOBAL restrict_fk_on_non_standard_key = OFF;` with '
                    . 'sufficient privileges), re-run the Shopware update, and revert the setting afterwards '
                    . 'if desired. See https://github.com/shopware/shopware/issues/13039 and the 6.7.3.0 '
                    . 'section of UPGRADE-6.7.md for details.',
                    $e
                );
            }

            throw $e;
        }
    }

    private function isMysqlNonStandardKeyRestriction(DriverException $e, Connection $connection): bool
    {
        // MySQL error 1553: ER_DROP_INDEX_FK. The message includes "<unknown key name>" when the
        // restriction is engaged spuriously (bug #118151). We match both to avoid false positives
        // on legitimate 1553 errors.
        if ($e->getCode() !== 1553 && !str_contains($e->getMessage(), '1553')) {
            return false;
        }

        if (!str_contains($e->getMessage(), 'unknown key name')
            && !str_contains($e->getMessage(), 'foreign key constraint')
        ) {
            return false;
        }

        try {
            $value = $connection->fetchOne('SELECT @@SESSION.restrict_fk_on_non_standard_key');
        } catch (\Throwable) {
            // Variable does not exist on this server version (MySQL < 8.4 / MariaDB).
            // If we got here at all the restriction is almost certainly the cause,
            // but be conservative: only surface the helper message when we can confirm
            // the variable exists and is ON.
            return false;
        }

        return (string) $value === '1' || strcasecmp((string) $value, 'ON') === 0;
    }
}
