<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1536233370EventAction extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536233370;
    }

    public function update(Connection $connection): void
    {
        $sql = <<<'SQL'
            CREATE TABLE `event_action` (
              `id` BINARY(16) NOT NULL PRIMARY KEY,
              `event_name` VARCHAR(500) NOT NULL,
              `action_name` VARCHAR(500) NOT NULL,
              `config` JSON NULL,
              `created_at` DATETIME(3) NOT NULL,
              `updated_at` DATETIME(3) NULL,
              INDEX `idx.event_action.event_name` (`event_name`),
              INDEX `idx.event_action.action_name` (`action_name`),
              CONSTRAINT `json.event_action.config` CHECK(JSON_VALID(`config`))
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;

        $connection->executeStatement($sql);
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
