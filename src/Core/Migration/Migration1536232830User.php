<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1536232830User extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536232830;
    }

    public function update(Connection $connection): void
    {
        $connection->executeQuery('
            CREATE TABLE `user` (
              `id` BINARY(16) NOT NULL,
              `username` VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
              `password` VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
              `last_login` DATETIME(3) NULL,
              `name` VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
              `email` VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
              `active` TINYINT(1) NOT NULL DEFAULT 0,
              `failed_logins` INT(11) NOT NULL DEFAULT 0,
              `locked_until` DATETIME(3) NULL,
              `locale_id` BINARY(16) NOT NULL,
              `attributes` JSON NULL,
              `created_at` DATETIME(3) NOT NULL,
              `updated_at` DATETIME(3) NULL,
              PRIMARY KEY (`id`),
              CONSTRAINT `json.attributes` CHECK (JSON_VALID(`attributes`)),
              CONSTRAINT `fk.user.locale_id` FOREIGN KEY (`locale_id`)
                REFERENCES `locale` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
