<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1536233280CustomFieldSet extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536233280;
    }

    public function update(Connection $connection): void
    {
        $connection->exec('
            CREATE TABLE `custom_field_set` (
              `id` BINARY(16) NOT NULL PRIMARY KEY,
              `name` VARCHAR(255) NOT NULL,
              `config` JSON NULL,
              `active` TINYINT(1) NOT NULL DEFAULT 1,
              `created_at` DATETIME(3) NOT NULL,
              `updated_at` DATETIME(3),
              CONSTRAINT `json.custom_field_set.config` CHECK(JSON_VALID(`config`))
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
