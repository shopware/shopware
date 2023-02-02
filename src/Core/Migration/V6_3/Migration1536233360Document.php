<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1536233360Document extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536233360;
    }

    public function update(Connection $connection): void
    {
        $sql = <<<'SQL'
            CREATE TABLE `document_type` (
              `id` BINARY(16) NOT NULL,
              `technical_name` VARCHAR(255) NOT NULL,
              `created_at` DATETIME(3) NOT NULL,
              `updated_at` DATETIME(3) NULL,
              PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL;
        $connection->executeUpdate($sql);

        $sql = <<<'SQL'
            CREATE TABLE `document_type_translation` (
              `document_type_id` BINARY(16) NOT NULL,
              `language_id` BINARY(16) NOT NULL,
              `name` VARCHAR(255) NOT NULL,
              `custom_fields` JSON NULL,
              `created_at` DATETIME(3) NOT NULL,
              `updated_at` DATETIME(3) NULL,
              PRIMARY KEY (`document_type_id`, `language_id`),
              CONSTRAINT `json.document_type_translation.custom_fields` CHECK (JSON_VALID(`custom_fields`)),
              CONSTRAINT `fk.document_type_translation.document_type_id` FOREIGN KEY (`document_type_id`)
                REFERENCES `document_type` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
              CONSTRAINT `fk.document_type_translation.language_id` FOREIGN KEY (`language_id`)
                REFERENCES `language` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL;
        $connection->executeUpdate($sql);

        $sql = <<<'SQL'
            CREATE TABLE `document` (
              `id` BINARY(16) NOT NULL,
              `document_type_id` BINARY(16) NOT NULL,
              `referenced_document_id` BINARY(16) NULL ,
              `file_type` VARCHAR(255) NOT NULL,
              `order_id` BINARY(16) NOT NULL,
              `order_version_id` BINARY(16) NOT NULL,
              `config` JSON NULL,
              `sent` TINYINT(1) NOT NULL DEFAULT 0,
              `static` TINYINT(1) NOT NULL DEFAULT 0,
              `deep_link_code` VARCHAR(32) NOT NULL,
              `document_media_file_id` BINARY(16) NULL,
              `custom_fields` JSON NULL,
              `created_at` DATETIME(3) NOT NULL,
              `updated_at` DATETIME(3) NULL,
              PRIMARY KEY (`id`),
              UNIQUE `uniq.deep_link_code` (`deep_link_code`),
              CONSTRAINT `json.document.custom_fields` CHECK (JSON_VALID(`custom_fields`)),
              CONSTRAINT `json.document.config` CHECK (JSON_VALID(`config`)),
              CONSTRAINT `fk.document.document_type_id` FOREIGN KEY (`document_type_id`)
                REFERENCES `document_type` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
              CONSTRAINT `fk.document.referenced_document_id` FOREIGN  KEY (`referenced_document_id`)
                REFERENCES `document` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
              CONSTRAINT `fk.document.order_id` FOREIGN KEY (`order_id`,`order_version_id`)
                REFERENCES `order` (`id`,`version_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
              CONSTRAINT `fk.document.document_media_file_id` FOREIGN KEY (`document_media_file_id`)
                REFERENCES `media` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL;
        $connection->executeUpdate($sql);
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
