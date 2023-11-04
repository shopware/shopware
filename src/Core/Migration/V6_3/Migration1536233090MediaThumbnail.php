<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1536233090MediaThumbnail extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536233090;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            CREATE TABLE `media_thumbnail` (
              `id` BINARY(16) NOT NULL,
              `media_id` BINARY(16) NOT NULL,
              `width` INT(10) unsigned NOT NULL,
              `height` INT(10) unsigned NOT NULL,
              `custom_fields` JSON NULL,
              `created_at` DATETIME(3) NOT NULL,
              `updated_at` DATETIME(3) NULL,
               PRIMARY KEY (`id`),
               CONSTRAINT `json.media_thumbnail.custom_fields` CHECK (JSON_VALID(`custom_fields`)),
               CONSTRAINT `fk.media_thumbnail.media_id` FOREIGN KEY (`media_id`)
                 REFERENCES `media` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
