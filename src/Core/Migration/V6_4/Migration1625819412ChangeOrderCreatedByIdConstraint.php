<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_4;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1625819412ChangeOrderCreatedByIdConstraint extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1625819412;
    }

    public function update(Connection $connection): void
    {
        $connection->executeUpdate('ALTER TABLE `order` DROP FOREIGN KEY `fk.order.created_by_id`');

        $connection->executeUpdate('ALTER TABLE `order` ADD CONSTRAINT `fk.order.created_by_id` FOREIGN KEY (`created_by_id`) REFERENCES `user` (`id`) ON DELETE SET NULL ON UPDATE CASCADE');

        $connection->executeUpdate('ALTER TABLE `order` DROP FOREIGN KEY `fk.order.updated_by_id`');

        $connection->executeUpdate('ALTER TABLE `order` ADD CONSTRAINT `fk.order.updated_by_id` FOREIGN KEY (`updated_by_id`) REFERENCES `user` (`id`) ON DELETE SET NULL ON UPDATE CASCADE');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
