<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1536233320DeadMessage extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536233320;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            CREATE TABLE `dead_message` (
              `id` BINARY(16) NOT NULL,
              `original_message_class` LONGTEXT COLLATE utf8mb4_unicode_ci NOT NULL,
              `serialized_original_message` LONGBLOB NOT NULL,
              `handler_class` LONGTEXT COLLATE utf8mb4_unicode_ci NOT NULL,
              `encrypted` TINYINT(1) DEFAULT \'0\' NOT NULL,
              `error_count` INT(11) NOT NULL,
              `next_execution_time` DATETIME(3) NOT NULL,
              `exception` LONGTEXT COLLATE utf8mb4_unicode_ci NOT NULL,
              `exception_message` LONGTEXT COLLATE utf8mb4_unicode_ci NOT NULL,
              `exception_file` LONGTEXT COLLATE utf8mb4_unicode_ci NOT NULL,
              `exception_line` INT(11) NOT NULL,
              `scheduled_task_id` BINARY(16) NULL,
              `created_at` DATETIME(3) NOT NULL,
              `updated_at` DATETIME(3),
              PRIMARY KEY (`id`),
              CONSTRAINT `check.dead_message.error_count` CHECK (error_count >= 1),
              CONSTRAINT `fk.dead_message.scheduled_task_id` FOREIGN KEY (scheduled_task_id)
                REFERENCES `scheduled_task` (id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
