<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Util\Database\TableHelper;

/**
 * @internal
 */
#[Package('inventory')]
class Migration1763125891AddProductTypeColumn extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1763125891;
    }

    public function update(Connection $connection): void
    {
        // MySQL 8.4 introduced `restrict_fk_on_non_standard_key` (default ON) which
        // refuses ALTER TABLE on a parent table when any child FK references a
        // non-standard key. Older shops carry such drifted FKs from past migrations
        // and we cannot safely repair them here. Relax the guard for this session
        // only — see issue #16240 and MySQL bug #118151.
        $previousGuard = $this->disableNonStandardFkGuard($connection);

        try {
            if (!TableHelper::columnExists($connection, 'product', 'type')) {
                $this->addColumn(
                    $connection,
                    'product',
                    'type',
                    'VARCHAR(32)',
                    false,
                    '\'physical\''
                );

                $connection->executeStatement('CREATE INDEX `idx.product.type` ON `product` (`type`)');
            }

            if (!TableHelper::indexExists($connection, 'product', 'idx.product.type')) {
                $connection->executeStatement('CREATE INDEX `idx.product.type` ON `product` (`type`)');
            }

            $batchSize = 5000;

            do {
                $affected = $connection->executeStatement(
                    "UPDATE `product`
                     SET `product`.`type` = 'digital'
                     WHERE `type` <> 'digital' AND JSON_CONTAINS(states, '\"is-download\"')
                     ORDER BY `id`
                     LIMIT {$batchSize};"
                );
            } while ($affected > 0);
        } finally {
            $this->restoreNonStandardFkGuard($connection, $previousGuard);
        }
    }

    /**
     * @return int|null previous value of the guard, or null if the variable is unsupported (MariaDB / MySQL <8.4)
     */
    private function disableNonStandardFkGuard(Connection $connection): ?int
    {
        try {
            $previous = $connection->fetchOne('SELECT @@SESSION.restrict_fk_on_non_standard_key');
        } catch (\Throwable) {
            return null;
        }

        if ($previous === false || $previous === null) {
            return null;
        }

        $connection->executeStatement('SET SESSION restrict_fk_on_non_standard_key = OFF');

        return (int) $previous;
    }

    private function restoreNonStandardFkGuard(Connection $connection, ?int $previous): void
    {
        if ($previous === null) {
            return;
        }

        $connection->executeStatement(\sprintf(
            'SET SESSION restrict_fk_on_non_standard_key = %d',
            $previous
        ));
    }
}
