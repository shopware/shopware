<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_4;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('core')]
class Migration1625819412ChangeOrderCreatedByIdConstraint extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1625819412;
    }

    public function update(Connection $connection): void
    {
        $this->dropForeignKeyIfExists($connection, 'order', 'fk.order.created_by_id');

        $connection->executeStatement('ALTER TABLE `order` ADD CONSTRAINT `fk.order.created_by_id` FOREIGN KEY (`created_by_id`) REFERENCES `user` (`id`) ON DELETE SET NULL ON UPDATE CASCADE');

        $this->dropForeignKeyIfExists($connection, 'order', 'fk.order.updated_by_id');

        $connection->executeStatement('ALTER TABLE `order` ADD CONSTRAINT `fk.order.updated_by_id` FOREIGN KEY (`updated_by_id`) REFERENCES `user` (`id`) ON DELETE SET NULL ON UPDATE CASCADE');
    }
}
