<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_4;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1619070236AppCmsBlock extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1619070236;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            CREATE TABLE IF NOT EXISTS `app_cms_block` (
                `id`            BINARY(16)      NOT NULL,
                `app_id`        BINARY(16)      NOT NULL,
                `name`          VARCHAR(255)    NOT NULL,
                `block`         JSON            NOT NULL,
                `template`      LONGTEXT        NOT NULL,
                `styles`        LONGTEXT        NOT NULL,
                `created_at`    DATETIME(3)     NOT NULL,
                `updated_at`    DATETIME(3)     NULL,
                PRIMARY KEY (`id`),
                KEY `fk.app_cms_block.app_id` (`app_id`),
                CONSTRAINT `fk.app_cms_block.app_id` FOREIGN KEY (`app_id`) REFERENCES `app` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `json.app_cms_block.block` CHECK (JSON_VALID(`block`))
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');

        $connection->executeStatement('
            CREATE TABLE IF NOT EXISTS `app_cms_block_translation` (
                `label`             VARCHAR(255)    NOT NULL,
                `app_cms_block_id`  BINARY(16)      NOT NULL,
                `language_id`       BINARY(16)      NOT NULL,
                `created_at`        DATETIME(3)     NOT NULL,
                `updated_at`        DATETIME(3)     NULL,
                PRIMARY KEY (`app_cms_block_id`, `language_id`),
                KEY `fk.app_cms_block_translation.app_cms_block_id` (`app_cms_block_id`),
                KEY `fk.app_cms_block_translation.language_id` (`language_id`),
                CONSTRAINT `fk.app_cms_block.app_cms_block_id` FOREIGN KEY (`app_cms_block_id`) REFERENCES `app_cms_block` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `fk.app_cms_block.language_id` FOREIGN KEY (`language_id`) REFERENCES `language` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
