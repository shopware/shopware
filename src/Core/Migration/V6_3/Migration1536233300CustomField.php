<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1536233300CustomField extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536233300;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            CREATE TABLE `custom_field` (
              `id` BINARY(16) NOT NULL PRIMARY KEY,
              `name` VARCHAR(255) NOT NULL,
              `type` VARCHAR(255) NOT NULL,
              `config` JSON NULL,
              `active` TINYINT(1) NOT NULL DEFAULT 1,
              `set_id` BINARY(16) NULL,
              `created_at` DATETIME(3) NOT NULL,
              `updated_at` DATETIME(3) NULL,
              CONSTRAINT `uniq.custom_field.name` UNIQUE  (`name`),
              CONSTRAINT `json.custom_field.config` CHECK(JSON_VALID(`config`)),
              CONSTRAINT `fk.custom_field.set_id` FOREIGN KEY (set_id)
                REFERENCES `custom_field_set` (id) ON UPDATE CASCADE ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
