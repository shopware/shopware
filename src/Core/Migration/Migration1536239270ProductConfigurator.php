<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1536239270ProductConfigurator extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536239270;
    }

    public function update(Connection $connection): void
    {
        $connection->executeQuery('
            CREATE TABLE `product_configurator` (
              `id` binary(16) NOT NULL,
              `version_id` binary(16) NOT NULL,
              `product_id` binary(16) NOT NULL,
              `product_version_id` binary(16) NOT NULL,
              `configuration_group_option_id` binary(16) NOT NULL,
              `price` JSON NULL,
              `prices` JSON NULL,
              `created_at` datetime(3) NOT NULL,
              `updated_at` datetime(3),
              PRIMARY KEY (`id`, `version_id`),
              CONSTRAINT `json.price` CHECK (JSON_VALID(`price`)),
              CONSTRAINT `json.prices` CHECK (JSON_VALID(`prices`)),
              CONSTRAINT `fk.product_configurator.product_id` FOREIGN KEY (`product_id`, `product_version_id`) REFERENCES `product` (`id`, `version_id`) ON DELETE CASCADE ON UPDATE CASCADE,
              CONSTRAINT `fk.product_configurator.configuration_group_option_id` FOREIGN KEY (`configuration_group_option_id`) REFERENCES `configuration_group_option` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            );
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
