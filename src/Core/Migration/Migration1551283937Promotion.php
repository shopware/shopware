<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1551283937Promotion extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1551283937;
    }

    public function update(Connection $connection): void
    {
        $connection->executeQuery('
        CREATE TABLE IF NOT EXISTS `promotion` (
              `id` BINARY(16) NOT NULL,
              `active` TINYINT(1) NOT NULL DEFAULT 0,
              `valid_from` DATETIME NULL,
              `valid_until` DATETIME NULL,
              `redeemable` INT NULL DEFAULT 1,
              `exclusive` TINYINT(1) NOT NULL DEFAULT 0,
              `priority` INT NOT NULL DEFAULT 0,
              `exclude_lower_priority` TINYINT(1) NOT NULL DEFAULT 0,
              `code` VARCHAR(255) NULL UNIQUE,
              `use_codes` TINYINT(1) NOT NULL DEFAULT 0,
              `created_at` DATETIME(3) NOT NULL,
              `updated_at` DATETIME(3) NULL,
              PRIMARY KEY (`id`)
              ) ENGINE = InnoDB');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
