<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1536233250MessageQueueStats extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536233250;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(
            'CREATE TABLE message_queue_stats (
               `id` BINARY(16) NOT NULL PRIMARY KEY,
               `name` VARCHAR(255) NOT NULL,
               `size` INT(11) NOT NULL DEFAULT 0,
               `created_at` DATETIME(3) NOT NULL,
               `updated_at` DATETIME(3) NULL,
               CONSTRAINT `uniq.message_queue_stats.name` UNIQUE(`name`),
               CONSTRAINT `check.message_queue_stats.size` CHECK (size >= 0)
             ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;'
        );
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
