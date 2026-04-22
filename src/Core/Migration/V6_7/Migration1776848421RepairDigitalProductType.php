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
class Migration1776848421RepairDigitalProductType extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1776848421;
    }

    public function update(Connection $connection): void
    {
        // Re-runs the is-download backfill for shops whose earlier run of
        // Migration1763125891AddProductTypeColumn terminated early and left some
        // download products with `type = 'physical'`.
        if (!TableHelper::columnExists($connection, 'product', 'type')
            || !TableHelper::columnExists($connection, 'product', 'states')
        ) {
            return;
        }

        $batchSize = 5000;
        $hasAffected = false;

        do {
            $affected = $connection->executeStatement(
                "UPDATE `product`
                 SET `product`.`type` = 'digital'
                 WHERE `type` <> 'digital' AND JSON_CONTAINS(states, '\"is-download\"')
                 ORDER BY `id`
                 LIMIT {$batchSize};"
            );

            if ($hasAffected === false && $affected > 0) {
                $hasAffected = true;
            }
        } while ($affected > 0);

        if ($hasAffected) {
            $this->registerIndexer($connection, 'product.indexer', ['product.states']);
        }
    }
}
