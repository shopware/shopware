<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_8;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 *
 * @no-indexer-required: restock_time is not part of any indexer output; it is read live by cart and storefront.
 */
#[Package('inventory')]
class Migration1784628114ProductRestockTimeMinValue extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1784628114;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('UPDATE `product` SET `restock_time` = NULL WHERE `restock_time` < 0');
    }
}
