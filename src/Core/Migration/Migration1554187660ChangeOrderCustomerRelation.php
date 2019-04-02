<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1554187660ChangeOrderCustomerRelation extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1554187660;
    }

    public function update(Connection $connection): void
    {
        $connection->executeUpdate('
ALTER TABLE `order_customer`
ADD `order_id` binary(16) NOT NULL AFTER `customer_id`,
ADD `order_version_id` binary(16) NOT NULL AFTER `order_id`;        
        ');

        $connection->executeUpdate('
ALTER TABLE `order_customer`
ADD CONSTRAINT `fk.order_customer.order_id`
FOREIGN KEY (`order_id`, `order_version_id`) REFERENCES `order` (`id`, `version_id`) ON DELETE CASCADE ON UPDATE CASCADE
        ');

        $connection->executeUpdate('ALTER TABLE `order` DROP FOREIGN KEY `fk.order.order_customer_id`');

        $connection->executeUpdate('
ALTER TABLE `order`
DROP `order_customer_id`,
DROP `order_customer_version_id`;        
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
