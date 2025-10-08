<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('discovery')]
class Migration1758018340ContentRoute extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1758018340;
    }

    public function update(Connection $connection): void
    {
        $sql = <<<'SQL'
            CREATE TABLE IF NOT EXISTS `content_route` (
                `id` BINARY(16) NOT NULL,
                `name` VARCHAR(255) NOT NULL,
                `url_pattern` VARCHAR(255) NOT NULL,
                `parameter_binding` JSON NOT NULL,
                `layout_id` BINARY(16) NULL,
                `layout_cascade` JSON NULL,
                `priority` INT NOT NULL DEFAULT 0,
                `overrides` JSON NULL,
                `active` TINYINT(1) NOT NULL DEFAULT 1,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                INDEX `idx.content_route.active` (`active`),
                CONSTRAINT `fk.content_route.layout_id` FOREIGN KEY (`layout_id`)
                    REFERENCES `content_layout` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        SQL;

        $connection->executeStatement($sql);
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
