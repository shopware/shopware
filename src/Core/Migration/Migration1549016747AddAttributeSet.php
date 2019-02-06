<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1549016747AddAttributeSet extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1549016747;
    }

    public function update(Connection $connection): void
    {
        $connection->exec('
            CREATE TABLE `attribute_set` (
              `id` binary(16) NOT NULL PRIMARY KEY,
              `config` JSON NULL,
              `created_at` datetime(3) NOT NULL,
              `updated_at` datetime(3),
              CONSTRAINT `json.config` CHECK(JSON_VALID(`config`))
            )
        ');

        $connection->exec('
            CREATE TABLE `attribute_set_relation` (
              `id` binary(16) NOT NULL PRIMARY KEY,
              `set_id` binary(16) NOT NULL,
              `entity_name` varchar(64) NOT NULL,
              `created_at` datetime(3) NOT NULL,
              `updated_at` datetime(3),
              CONSTRAINT `uniq.attribute_set_relation.entity_name`
                UNIQUE (`set_id`, `entity_name`),
              CONSTRAINT `fk.attribute_set_relation.set_id`
                FOREIGN KEY (set_id) REFERENCES `attribute_set` (id)
                ON UPDATE CASCADE ON DELETE CASCADE
            )
        ');

        $connection->exec('
            ALTER TABLE `attribute`
            ADD COLUMN `set_id` binary(16) DEFAULT NULL,
            ADD CONSTRAINT `fk.attribute.set_id`
              FOREIGN KEY (set_id) REFERENCES `attribute_set` (id)
              ON UPDATE CASCADE ON DELETE CASCADE
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
