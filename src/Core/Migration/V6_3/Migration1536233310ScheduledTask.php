<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1536233310ScheduledTask extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536233310;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            CREATE TABLE `scheduled_task` (
              `id` BINARY(16) NOT NULL,
              `name` VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
              `scheduled_task_class` VARCHAR(512) COLLATE utf8mb4_unicode_ci NOT NULL,
              `run_interval` INT(11) NOT NULL,
              `status` VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
              `last_execution_time` DATETIME(3),
              `next_execution_time` DATETIME(3) NOT NULL,
              `created_at` DATETIME(3) NOT NULL,
              `updated_at` DATETIME(3) NULL,
              PRIMARY KEY (`id`),
              CONSTRAINT `check.scheduled_task.run_interval` CHECK (run_interval >= 1),
              CONSTRAINT `uniq.scheduled_task.scheduled_task_class` UNIQUE (scheduled_task_class)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
