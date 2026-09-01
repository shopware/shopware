<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('framework')]
class Migration1787465993AddPluginMigrationTable extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1787465993;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS `plugin_migration` (
                `plugin_name`        VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
                `migration_class`    VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
                `creation_timestamp` INT UNSIGNED                            NOT NULL,
                `executed_at`        DATETIME(3)                             NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
                PRIMARY KEY (`plugin_name`, `migration_class`),
                UNIQUE KEY `uniq.plugin_migration.plugin_name__creation_timestamp` (`plugin_name`, `creation_timestamp`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            SQL);
    }
}
