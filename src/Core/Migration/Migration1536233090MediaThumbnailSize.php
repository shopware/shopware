<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1536233090MediaThumbnailSize extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536233090;
    }

    public function update(Connection $connection): void
    {
        $connection->exec('
            CREATE TABLE `media_thumbnail_size`(
              `id` BINARY(16) NOT NULL,
              `width` INT(11) NOT NULL,
              `height` INT(11) NOT NULL,
              `attributes` JSON NULL,
              `created_at` DATETIME(3) NOT NULL,
              `updated_at` DATETIME(3) NULL,
              PRIMARY KEY (`id`),
              CONSTRAINT `uniq.width` UNIQUE (`width`, `height`),
              CONSTRAINT `json.attributes` CHECK (JSON_VALID(`attributes`))
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // no destructive changes
    }
}
