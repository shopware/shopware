<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1550672025Document extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1550672025;
    }

    public function update(Connection $connection): void
    {
        $sql = <<<SQL
CREATE TABLE `document` (
  `id` BINARY(16) NOT NULL,
  `type` VARCHAR(255) NOT NULL,
  `order_id` BINARY(16) NOT NULL,
  `order_version_id` BINARY(16) NOT NULL,
  `config` JSON NULL,
  `sent` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` DATETIME(3) NOT NULL,
  PRIMARY KEY (`id`),
  CONSTRAINT `json.document.config` CHECK (JSON_VALID(`config`)),
  CONSTRAINT `fk.document.order_id` FOREIGN KEY (`order_id`,`order_version_id`)
    REFERENCES `order` (`id`,`version_id`) ON DELETE RESTRICT ON UPDATE CASCADE  
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL;
        $connection->executeUpdate($sql);
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
