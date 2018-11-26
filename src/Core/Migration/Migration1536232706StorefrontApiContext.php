<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1536232706StorefrontApiContext extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536232706;
    }

    public function update(Connection $connection): void
    {
        $connection->executeQuery('
            CREATE TABLE `storefront_api_context` (
              `token` binary(16) NOT NULL,
              `payload` JSON NOT NULL,
              PRIMARY KEY (`token`),
              CONSTRAINT `json.payload` CHECK (JSON_VALID(`payload`))
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
