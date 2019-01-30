<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1548855440AddNavigationTranslation extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1548855440;
    }

    public function update(Connection $connection): void
    {
        $connection->executeQuery('
            CREATE TABLE `navigation_translation` (
              `navigation_id` binary(16) NOT NULL,
              `navigation_version_id` binary(16) NOT NULL,
              `language_id` binary(16) NOT NULL,
              `name` varchar(255) COLLATE utf8mb4_unicode_ci NULL,
              `created_at` datetime(3) NOT NULL,
              `updated_at` datetime(3),
              PRIMARY KEY (`navigation_id`, `navigation_version_id`, `language_id`),
              CONSTRAINT `fk.navigation_translation.language_id` FOREIGN KEY (`language_id`) REFERENCES `language` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
              CONSTRAINT `fk.navigation_translation.navigation_id` FOREIGN KEY (`navigation_id`, `navigation_version_id`) REFERENCES `navigation` (`id`, `version_id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
