<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_4;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1620632460AddedCategoryIds extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1620632460;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('ALTER TABLE `product` ADD `category_ids` json NULL AFTER `category_tree`');
        $this->registerIndexer($connection, 'product.indexer');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
