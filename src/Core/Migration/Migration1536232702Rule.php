<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1536232702Rule extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536232702;
    }

    public function update(Connection $connection): void
    {
        $connection->executeQuery('
            CREATE TABLE `rule` (
              `id` binary(16) NOT NULL,
              `name` varchar(500) NOT NULL,
              `type` VARCHAR(256) NULL,
              `description` LONGTEXT NULL,
              `priority` int(11) NOT NULL,
              `payload` JSON NULL,
              `created_at` datetime(3) NOT NULL,
              `updated_at` datetime(3) NULL,
              PRIMARY KEY (`id`),
              CONSTRAINT `json.payload` CHECK (JSON_VALID(`payload`))
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
