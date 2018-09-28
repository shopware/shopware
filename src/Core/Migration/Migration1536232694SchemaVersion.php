<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1536232694SchemaVersion extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536232694;
    }

    public function update(Connection $connection): void
    {
        $connection->executeQuery('
            CREATE TABLE `schema_version` (
              `version` varchar(14) COLLATE utf8mb4_unicode_ci NOT NULL,
              `start_date` datetime(3) NOT NULL,
              `complete_date` datetime(3) DEFAULT NULL,
              `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
              `error_msg` longtext COLLATE utf8mb4_unicode_ci
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
