<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1549893754AddMailTemplate extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1549893754;
    }

    public function update(Connection $connection): void
    {
        $connection->executeQuery('
            CREATE TABLE `mail_template` (
              `id` BINARY(16) NOT NULL,
              `mail_type` VARCHAR(255) NULL,
              `system_default` TINYINT(1) unsigned NOT NULL DEFAULT \'0\',
              `created_at` DATETIME(3) NOT NULL,
              `updated_at` DATETIME(3),
              PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
