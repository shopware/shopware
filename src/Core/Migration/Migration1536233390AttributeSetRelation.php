<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1536233390AttributeSetRelation extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536233390;
    }

    public function update(Connection $connection): void
    {
        $connection->exec('
            CREATE TABLE `attribute_set_relation` (
              `id` BINARY(16) NOT NULL,
              `set_id` BINARY(16) NOT NULL,
              `entity_name` VARCHAR(64) NOT NULL,
              `created_at` DATETIME(3) NOT NULL,
              `updated_at` DATETIME(3) NULL,
              PRIMARY KEY(`id`),
              CONSTRAINT `uniq.attribute_set_relation.entity_name`
                UNIQUE (`set_id`, `entity_name`),
              CONSTRAINT `fk.attribute_set_relation.set_id` FOREIGN KEY (`set_id`) 
                REFERENCES `attribute_set` (id) ON UPDATE CASCADE ON DELETE CASCADE
            );
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
