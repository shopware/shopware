<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1536233070MediaFolderConfiguration extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536233070;
    }

    public function update(Connection $connection): void
    {
        $connection->exec('
            CREATE TABLE `media_folder_configuration` (
              `id` BINARY(16),
              `create_thumbnails` TINYINT(1) DEFAULT 1,
              `thumbnail_quality` INT(11) DEFAULT 80,
              `keep_aspect_ratio`  TINYINT(1) DEFAULT 1,
              `attributes` JSON NULL,
              `created_at` DATETIME(3) NOT NULL,
              `updated_at` DATETIME(3) NULL,
              PRIMARY KEY (`id`),
              CONSTRAINT `check.thumbnail_quality` CHECK (thumbnail_quality > 0 AND thumbnail_quality < 100),
              CONSTRAINT `check.width` CHECK (width >= 1),
              CONSTRAINT `check.height` CHECK (height >= 1),
              CONSTRAINT `json.attributes` CHECK (JSON_VALID(`attributes`))
            );
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // no destructive changes
    }
}
