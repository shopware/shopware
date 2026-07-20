<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_8;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('inventory')]
class Migration1773829004RegisterProductStreamIndexer extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1773829004;
    }

    public function update(Connection $connection): void
    {
        // Similar to \Shopware\Core\Migration\V6_8\Migration1763125892RemoveProductStatesColumn::update
        // Re-register the rule indexer to ensure product stream which might depend on the removed column are re-indexed properly
        $this->registerIndexer($connection, 'product_stream.indexer');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
