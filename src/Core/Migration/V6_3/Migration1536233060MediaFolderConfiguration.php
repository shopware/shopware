<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1536233060MediaFolderConfiguration extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536233060;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            CREATE TABLE `media_folder_configuration` (
              `id` BINARY(16),
              `create_thumbnails` TINYINT(1) DEFAULT 1,
              `thumbnail_quality` INT(11) DEFAULT 80,
              `media_thumbnail_sizes_ro` LONGBLOB NULL,
              `keep_aspect_ratio`  TINYINT(1) DEFAULT 1,
              `private`  TINYINT(1) DEFAULT 0,
              `custom_fields` JSON NULL,
              `created_at` DATETIME(3) NOT NULL,
              `updated_at` DATETIME(3) NULL,
              PRIMARY KEY (`id`),
              CONSTRAINT `json.media_folder_configuration.custom_fields` CHECK (JSON_VALID(`custom_fields`))
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // no destructive changes
    }
}
