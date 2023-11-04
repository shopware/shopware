<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_4;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1667806582AddCreatedByIdAndUpdatedByIdToCustomer extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1667806582;
    }

    public function update(Connection $connection): void
    {
        if ($this->columnExists($connection, 'customer', 'created_by_id') || $this->columnExists($connection, 'customer', 'updated_by_id')) {
            return;
        }

        $connection->executeStatement('
            ALTER TABLE `customer`
            ADD COLUMN `created_by_id` BINARY(16) NULL AFTER `bound_sales_channel_id`,
            ADD COLUMN `updated_by_id` BINARY(16) NULL AFTER `created_by_id`;
        ');

        $connection->executeStatement('ALTER TABLE `customer` ADD CONSTRAINT `fk.customer.created_by_id` FOREIGN KEY (`created_by_id`)
              REFERENCES `user` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE');

        $connection->executeStatement('ALTER TABLE `customer` ADD CONSTRAINT `fk.customer.updated_by_id` FOREIGN KEY (`updated_by_id`)
              REFERENCES `user` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
