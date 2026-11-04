<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Shopware\Core\Content\Product\DataAbstractionLayer\CheapestPriceUpdater;
use Shopware\Core\Content\Product\Events\ProductIndexerEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * A parent with variants kept the cheapest price accessor it got before it had variants. {@see CheapestPriceUpdater}
 * removes it on indexing now, this removes it from existing products right away. The product indexer then runs again,
 * so the {@see ProductIndexerEvent} it dispatches refreshes the Elasticsearch documents of these parents too.
 *
 * @internal
 */
#[Package('framework')]
class Migration1791620738RemoveCheapestPriceAccessorOfParentProducts extends MigrationStep
{
    private const UPDATE_LIMIT = 1000;

    public function getCreationTimestamp(): int
    {
        return 1791620738;
    }

    public function update(Connection $connection): void
    {
        $hasOutdatedAccessors = $connection->fetchOne(
            'SELECT 1 FROM `product` WHERE `child_count` > 0 AND `cheapest_price_accessor` IS NOT NULL LIMIT 1'
        );
        if ($hasOutdatedAccessors === false) {
            return;
        }

        // a full run, as a list of updaters would replace the full run another migration of the same update registered;
        // registered before the accessors are removed, so a re-run after a failure still registers it
        $this->registerIndexer($connection, 'product.indexer');

        do {
            $affectedRows = (int) $connection->executeStatement(
                'UPDATE `product` SET `cheapest_price_accessor` = NULL
                 WHERE `child_count` > 0 AND `cheapest_price_accessor` IS NOT NULL
                 LIMIT :limit',
                ['limit' => self::UPDATE_LIMIT],
                ['limit' => ParameterType::INTEGER]
            );
        } while ($affectedRows === self::UPDATE_LIMIT);
    }
}
