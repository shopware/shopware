<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1536233290CustomFieldSetRelation extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536233290;
    }

    public function update(Connection $connection): void
    {
        $connection->exec('
            CREATE TABLE `custom_field_set_relation` (
              `id` BINARY(16) NOT NULL,
              `set_id` BINARY(16) NOT NULL,
              `entity_name` VARCHAR(64) NOT NULL,
              `created_at` DATETIME(3) NOT NULL,
              `updated_at` DATETIME(3) NULL,
              PRIMARY KEY(`id`),
              CONSTRAINT `uniq.custom_field_set_relation.entity_name`
                UNIQUE (`set_id`, `entity_name`),
              CONSTRAINT `fk.custom_field_set_relation.set_id` FOREIGN KEY (`set_id`) 
                REFERENCES `custom_field_set` (id) ON UPDATE CASCADE ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
