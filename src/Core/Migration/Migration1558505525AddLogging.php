<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1558505525AddLogging extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1558505525;
    }

    public function update(Connection $connection): void
    {
        $connection->executeQuery('
            CREATE TABLE `log_entry` (
              `id` BINARY(16) NOT NULL,
              `message` VARCHAR(255) NOT NULL,
              `level` TINYINT NOT NULL,
              `channel` VARCHAR(255) NOT NULL,
              `content` JSON NULL,
              `extra` JSON NULL,
              `created_at` DATETIME(3) NOT NULL,
              `updated_at` DATETIME(3) ,
              CONSTRAINT `json.logging.content` CHECK (JSON_VALID(`content`))
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
