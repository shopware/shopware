<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1536232710Language extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536232710;
    }

    public function update(Connection $connection): void
    {
        $connection->executeQuery('
            CREATE TABLE `language` (
              `id` binary(16) NOT NULL,
              `name` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
              `parent_id` binary(16) NULL DEFAULT NULL,
              `locale_id` binary(16) NULL,
              `created_at` datetime(3) NOT NULL,
              `updated_at` datetime(3),
              PRIMARY KEY (`id`),
              UNIQUE `uniq.locale_id` (`locale_id`),
              CONSTRAINT `fk.language.parent_id` FOREIGN KEY (`parent_id`) REFERENCES `language` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
              CONSTRAINT `fk.language.locale_id` FOREIGN KEY (`locale_id`) REFERENCES `locale` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
