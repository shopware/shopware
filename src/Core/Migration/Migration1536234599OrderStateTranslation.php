<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1536234599OrderStateTranslation extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536234599;
    }

    public function update(Connection $connection): void
    {
        $connection->executeQuery('
            CREATE TABLE `order_state_translation` (
              `order_state_id` binary(16) NOT NULL,
              `order_state_version_id` binary(16) NOT NULL,
              `language_id` binary(16) NOT NULL,
              `description` varchar(255) COLLATE utf8mb4_unicode_ci NULL,
              `created_at` datetime(3) NOT NULL,
              `updated_at` datetime(3),
              PRIMARY KEY (`order_state_id`, `language_id`),
              CONSTRAINT `fk.order_state_translation.language_id` FOREIGN KEY (`language_id`) REFERENCES `language` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
              CONSTRAINT `fk.order_state_translation.id` FOREIGN KEY (`order_state_id`, `order_state_version_id`) REFERENCES `order_state` (`id`, `version_id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
