<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1536232850Media extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536232850;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            CREATE TABLE `media` (
              `id`              BINARY(16)                                  NOT NULL,
              `user_id`         BINARY(16)                                  NULL,
              `media_folder_id` BINARY(16)                                  NULL,
              `mime_type`       VARCHAR(255)    COLLATE utf8mb4_unicode_ci  NULL,
              `file_extension`  VARCHAR(50)     COLLATE utf8mb4_unicode_ci  NULL,
              `file_size`       INT(10)         unsigned                    NULL,
              `meta_data`       JSON                                        NULL,
              `file_name`       LONGTEXT        COLLATE utf8mb4_unicode_ci  NULL,
              `media_type`      LONGBLOB                                    NULL,
              `thumbnails_ro`   LONGBLOB                                    NULL,
              `private`         TINYINT(1)                                  NOT NULL DEFAULT 0,
              `uploaded_at`     DATETIME(3)                                 NULL,
              `created_at`      DATETIME(3)                                 NOT NULL,
              `updated_at`      DATETIME(3)                                 NULL,
               PRIMARY KEY (`id`),
               CONSTRAINT `json.media.meta_data` CHECK (JSON_VALID(`meta_data`)),
               CONSTRAINT `fk.media.user_id` FOREIGN KEY (`user_id`)
                 REFERENCES `user` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
               CONSTRAINT `fk.media.media_folder_id` FOREIGN KEY (`media_folder_id`)
                 REFERENCES `media_folder` (`id`) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');

        $connection->executeStatement('
            CREATE TABLE `media_translation` (
              `media_id` BINARY(16) NOT NULL,
              `language_id` BINARY(16) NOT NULL,
              `alt` VARCHAR(255) COLLATE utf8mb4_unicode_ci,
              `title` VARCHAR(255) COLLATE  utf8mb4_unicode_ci NULL,
              `custom_fields` JSON NULL,
              `created_at` DATETIME(3) NOT NULL,
              `updated_at` DATETIME(3) NULL,
              PRIMARY KEY (`media_id`, `language_id`),
              CONSTRAINT `json.media_translation.custom_fields` CHECK (JSON_VALID(`custom_fields`)),
              CONSTRAINT `fk.media_translation.language_id` FOREIGN KEY (`language_id`)
                REFERENCES `language` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
              CONSTRAINT `fk.media_translation.media_id` FOREIGN KEY (`media_id`)
                REFERENCES `media` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');

        $connection->executeStatement('
            ALTER TABLE `user`
              ADD CONSTRAINT `fk.user.avatar_id` FOREIGN KEY (avatar_id)
                REFERENCES `media` (id) ON DELETE SET NULL;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
