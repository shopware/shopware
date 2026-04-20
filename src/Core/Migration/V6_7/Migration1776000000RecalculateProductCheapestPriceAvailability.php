<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Product\DataAbstractionLayer\ProductIndexer;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('framework')]
class Migration1776000000RecalculateProductCheapestPriceAvailability extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1776000000;
    }

    public function update(Connection $connection): void
    {
        $this->registerIndexer($connection, 'product.indexer', [ProductIndexer::CHEAPEST_PRICE_UPDATER]);
    }
}
