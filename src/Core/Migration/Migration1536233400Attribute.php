<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1536233400Attribute extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536233400;
    }

    public function update(Connection $connection): void
    {
        $connection->exec('
            CREATE TABLE `attribute` (
              `id` BINARY(16) NOT NULL PRIMARY KEY,
              `name` VARCHAR(255) NOT NULL,
              `type` VARCHAR(255) NOT NULL,
              `config` JSON NULL,
              `set_id` BINARY(16) NULL,
              `created_at` DATETIME(3) NOT NULL,
              `updated_at` DATETIME(3) NULL,
              CONSTRAINT `uniq.attribute.name` UNIQUE  (`name`),
              CONSTRAINT `JSON.config` CHECK(JSON_VALID(`config`)),
              CONSTRAINT `fk.attribute.set_id` FOREIGN KEY (set_id)
                REFERENCES `attribute_set` (id) ON UPDATE CASCADE ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
