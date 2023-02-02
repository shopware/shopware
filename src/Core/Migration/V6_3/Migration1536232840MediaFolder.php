<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1536232840MediaFolder extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536232840;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            CREATE TABLE `media_folder` (
              `id`                              BINARY(16)                              NOT NULL,
              `parent_id`                       BINARY(16)                              NULL,
              `default_folder_id`               BINARY(16)                              NULL,
              `name`                            VARCHAR(255) COLLATE utf8mb4_unicode_ci NULL,
              `child_count`                     INT(11) unsigned                        NOT NULL DEFAULT 0,
              `media_folder_configuration_id`   BINARY(16)                              NULL,
              `use_parent_configuration`        TINYINT(1)                              NULL DEFAULT 1,
              `custom_fields`                   JSON                                    NULL,
              `created_at`                      DATETIME(3)                             NOT NULL,
              `updated_at`                      DATETIME(3)                             NULL,
              PRIMARY KEY (`id`),
              CONSTRAINT `uniq.media_folder.default_folder_id` UNIQUE (`default_folder_id`),
              CONSTRAINT `json.media_folder.custom_fields` CHECK (JSON_VALID(`custom_fields`)),
              CONSTRAINT `fk.media_folder.parent_id` FOREIGN KEY (`parent_id`)
                REFERENCES `media_folder` (`id`) ON DELETE CASCADE,
              CONSTRAINT `fk.media_folder.default_folder_id` FOREIGN KEY (`default_folder_id`)
                REFERENCES `media_default_folder` (`id`) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // no destructive changes
    }
}
