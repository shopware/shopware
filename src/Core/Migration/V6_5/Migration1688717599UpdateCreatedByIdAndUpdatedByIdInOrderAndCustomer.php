<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_5;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('system-settings')]
class Migration1688717599UpdateCreatedByIdAndUpdatedByIdInOrderAndCustomer extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1688717599;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('ALTER TABLE `customer` DROP FOREIGN KEY `fk.customer.created_by_id`');

        $connection->executeStatement('ALTER TABLE `customer` ADD CONSTRAINT `fk.customer.created_by_id` FOREIGN KEY (`created_by_id`)
              REFERENCES `user` (`id`) ON DELETE SET NULL ON UPDATE CASCADE');

        $connection->executeStatement('ALTER TABLE `customer` DROP FOREIGN KEY `fk.customer.updated_by_id`');

        $connection->executeStatement('ALTER TABLE `customer` ADD CONSTRAINT `fk.customer.updated_by_id` FOREIGN KEY (`updated_by_id`)
              REFERENCES `user` (`id`) ON DELETE SET NULL ON UPDATE CASCADE');

        $connection->executeStatement('ALTER TABLE `order` DROP FOREIGN KEY `fk.order.created_by_id`');

        $connection->executeStatement('ALTER TABLE `order` ADD CONSTRAINT `fk.order.created_by_id` FOREIGN KEY (`created_by_id`)
              REFERENCES `user` (`id`) ON DELETE SET NULL ON UPDATE CASCADE');

        $connection->executeStatement('ALTER TABLE `order` DROP FOREIGN KEY `fk.order.updated_by_id`');

        $connection->executeStatement('ALTER TABLE `order` ADD CONSTRAINT `fk.order.updated_by_id` FOREIGN KEY (`updated_by_id`)
              REFERENCES `user` (`id`) ON DELETE SET NULL ON UPDATE CASCADE');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
