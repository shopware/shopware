<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

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
            'UPDATE `order` AS `o` SET `o`.`language_id` = COALESCE((
                SELECT `language_id` FROM `customer` AS `c` 
                    LEFT JOIN `order_customer` AS `oc` 
                        ON `c`.`id` = `oc`.`customer_id` WHERE `oc`.`order_id` = `o`.`id`  LIMIT 1
            ), (SELECT `id` FROM `language` AS `l` 
                    LEFT JOIN `sales_channel_language` AS `scl` 
                        ON `l`.`id` = `scl`.`language_id` WHERE `scl`.`sales_channel_id` = `o`.`sales_channel_id` LIMIT 1
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
