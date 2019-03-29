<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;

class Migration1553004192SalesChannelCustomerGroup extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1553004192;
    }

    public function update(Connection $connection): void
    {
        $connection->exec(
            'ALTER TABLE `sales_channel`
             ADD COLUMN `customer_group_id` BINARY(16) NOT NULL AFTER navigation_version_id;'
        );

        $connection->executeQuery('
            UPDATE `sales_channel`
            SET `customer_group_id` = :fallbackCustomerGroup
        ', [':fallbackCustomerGroup' => Uuid::fromHexToBytes(Defaults::FALLBACK_CUSTOMER_GROUP)]);

        $connection->exec(
            'ALTER TABLE `sales_channel`
             ADD CONSTRAINT `fk.sales_channel.customer_group_id`
                FOREIGN KEY (`customer_group_id`) 
                  REFERENCES `customer_group` (`id`)
                  ON DELETE RESTRICT
                  ON UPDATE CASCADE;'
        );
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
