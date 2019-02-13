<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1536233200ProductDatasheet extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536233200;
    }

    public function update(Connection $connection): void
    {
        $connection->executeQuery('
            CREATE TABLE `product_datasheet` (
              `product_id` BINARY(16) NOT NULL,
              `product_version_id` BINARY(16) NOT NULL,
              `configuration_group_option_id` BINARY(16) NOT NULL,
              PRIMARY KEY (`product_id`, `product_version_id`, `configuration_group_option_id`),
              CONSTRAINT `fk.product_datasheet.product_id` FOREIGN KEY (`product_id`, `product_version_id`)
                REFERENCES `product` (`id`, `version_id`) ON DELETE CASCADE ON UPDATE CASCADE,
              CONSTRAINT `fk.product_datasheet.configuration_option_id` FOREIGN KEY (`configuration_group_option_id`)
                REFERENCES `configuration_group_option` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            );
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
