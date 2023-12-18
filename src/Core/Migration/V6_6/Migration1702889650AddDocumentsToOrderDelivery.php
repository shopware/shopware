<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_6;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1702889650AddDocumentsToOrderDelivery extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1702889650;
    }

    public function update(Connection $connection): void
    {
        if ($this->columnExists($connection, 'document', 'order_delivery_id')) {
            return;
        }

        $sql = <<<SQL
ALTER TABLE `document`
    ADD COLUMN `order_delivery_id` BINARY(16) NULL AFTER `order_version_id`,
    ADD COLUMN `order_delivery_version_id` BINARY(16) NULL AFTER `order_delivery_id`,
    ADD CONSTRAINT `fk.document.order_delivery_id`
        FOREIGN KEY (`order_delivery_id`, `order_delivery_version_id`) REFERENCES `order_delivery` (`id`, `version_id`)
            ON UPDATE CASCADE;
SQL;

        $connection->executeStatement($sql);
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
