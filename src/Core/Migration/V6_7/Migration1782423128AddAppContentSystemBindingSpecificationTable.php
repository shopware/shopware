<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('framework')]
class Migration1782423128AddAppContentSystemBindingSpecificationTable extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1782423128;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            CREATE TABLE IF NOT EXISTS `app_content_system_binding_specification` (
                `id` BINARY(16) NOT NULL,
                `app_id` BINARY(16) NOT NULL,
                `name` VARCHAR(255) NOT NULL,
                `schema` JSON NOT NULL,
                `hash` VARCHAR(64) NOT NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                -- bindings are unique within their app, not globally; two apps may each ship the same id
                UNIQUE KEY `uniq.app_content_system_binding_specification.app_id_name` (`app_id`, `name`),
                CONSTRAINT `fk.app_content_system_binding_specification.app_id`
                    FOREIGN KEY (`app_id`) REFERENCES `app` (`id`)
                    ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
