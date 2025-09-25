<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

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
        if ($this->indexExists($connection, 'product', 'idx.product.categories')) {
            return;
        }

        $connection->executeStatement('CREATE INDEX `idx.product.categories` ON `product` (`categories`)');
    }
}
