<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_5;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('inventory')]
class Migration1678969082DropVariantListingFields extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1678969082;
    }

    public function update(Connection $connection): void
    {
    }

    public function updateDestructive(Connection $connection): void
    {
        if ($this->columnExists($connection, 'product', 'display_parent')) {
            $connection->executeStatement(
                'ALTER TABLE `product` DROP COLUMN `display_parent`'
            );
        }

        if ($this->columnExists($connection, 'product', 'configurator_group_config')) {
            $connection->executeStatement(
                'ALTER TABLE `product` DROP COLUMN `configurator_group_config`'
            );
        }

        if ($this->columnExists($connection, 'product', 'main_variant_id')) {
            $connection->executeStatement(
                'ALTER TABLE `product` DROP FOREIGN KEY `fk.product.main_variant_id`, DROP COLUMN `main_variant_id`'
            );
        }
    }
}
