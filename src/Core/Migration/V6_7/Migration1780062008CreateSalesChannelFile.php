<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('framework')]
class Migration1780062008CreateSalesChannelFile extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1780062008;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(
            <<<'SQL'
CREATE TABLE IF NOT EXISTS `sales_channel_file` (
    `id` BINARY(16) NOT NULL,
    `sales_channel_id` BINARY(16) NOT NULL,
    `file_family` VARCHAR(64) NOT NULL,
    `file_name` VARCHAR(512) NOT NULL,
    `enabled` TINYINT(1) NOT NULL DEFAULT 0,
    `template_overrides` JSON NULL,
    `created_at` DATETIME(3) NOT NULL,
    `updated_at` DATETIME(3) NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq.sales_channel_file.sc_id_family_file_name` (`sales_channel_id`, `file_family`, `file_name`),
    CONSTRAINT `json.sales_channel_file.template_overrides` CHECK (JSON_VALID(`template_overrides`)),
    CONSTRAINT `fk.sales_channel_file.sales_channel_id` FOREIGN KEY (`sales_channel_id`)
        REFERENCES `sales_channel` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL
        );
    }
}
