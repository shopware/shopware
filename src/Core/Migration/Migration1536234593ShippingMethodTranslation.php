<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1536234593ShippingMethodTranslation extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536234593;
    }

    public function update(Connection $connection): void
    {
        $connection->executeQuery('
            CREATE TABLE `shipping_method_translation` (
              `shipping_method_id` binary(16) NOT NULL,
              `language_id` binary(16) NOT NULL,
              `name` varchar(255) COLLATE utf8mb4_unicode_ci NULL,
              `description` mediumtext COLLATE utf8mb4_unicode_ci NULL,
              `comment` varchar(255) COLLATE utf8mb4_unicode_ci NULL,
              `created_at` datetime(3) NOT NULL,
              `updated_at` datetime(3),
              PRIMARY KEY (`shipping_method_id`, `language_id`),
              CONSTRAINT `fk.shipping_method_translation.language_id` FOREIGN KEY (`language_id`) REFERENCES `language` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
              CONSTRAINT `fk.shipping_method_translation.shipping_method_id` FOREIGN KEY (`shipping_method_id`) REFERENCES `shipping_method` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
