<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1536233220ProductConfigurator extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536233220;
    }

    public function update(Connection $connection): void
    {
        $connection->executeQuery('
            CREATE TABLE `product_configurator_setting` (
              `id` BINARY(16) NOT NULL,
              `version_id` BINARY(16) NOT NULL,
              `product_id` BINARY(16) NOT NULL,
              `product_version_id` BINARY(16) NOT NULL,
              `configuration_group_option_id` BINARY(16) NOT NULL,
              `price` JSON NULL,
              `prices` JSON NULL,
              `attributes` JSON NULL,
              `created_at` DATETIME(3) NOT NULL,
              `updated_at` DATETIME(3) NULL,
              PRIMARY KEY (`id`, `version_id`),
              CONSTRAINT `json.price` CHECK (JSON_VALID(`price`)),
              CONSTRAINT `json.prices` CHECK (JSON_VALID(`prices`)),
              CONSTRAINT `json.attributes` CHECK (JSON_VALID(`attributes`)),
              CONSTRAINT `fk.product_configurator_setting.product_id` FOREIGN KEY (`product_id`, `product_version_id`) 
                REFERENCES `product` (`id`, `version_id`) ON DELETE CASCADE ON UPDATE CASCADE,
              CONSTRAINT `fk.product_configurator_setting.configuration_group_option_id` FOREIGN KEY (`configuration_group_option_id`) 
                REFERENCES `configuration_group_option` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            );
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
