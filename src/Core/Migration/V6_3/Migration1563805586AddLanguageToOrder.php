<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1563805586AddLanguageToOrder extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1563805586;
    }

    public function update(Connection $connection): void
    {
        $connection->executeUpdate('ALTER TABLE `order` ADD `language_id` BINARY(16) AFTER `currency_id`');

        $connection->executeUpdate(
            'UPDATE `order` SET `order`.`language_id` = COALESCE((
                SELECT `language_id` FROM `customer`
                    LEFT JOIN `order_customer`
                        ON `customer`.`id` = `order_customer`.`customer_id` WHERE `order_customer`.`order_id` = `order`.`id`  LIMIT 1
            ), (SELECT `id` FROM `language`
                    LEFT JOIN `sales_channel_language`
                        ON `language`.`id` = `sales_channel_language`.`language_id` WHERE `sales_channel_language`.`sales_channel_id` = `order`.`sales_channel_id` LIMIT 1
            ))'
        );

        $connection->executeUpdate('ALTER TABLE `order` MODIFY COLUMN `language_id` BINARY(16) NOT NULL');

        $connection->executeUpdate('ALTER TABLE `order` ADD CONSTRAINT `fk.language_id` FOREIGN KEY (`language_id`)
              REFERENCES `language` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
