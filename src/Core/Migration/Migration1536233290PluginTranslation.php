<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1536233290PluginTranslation extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536233290;
    }

    public function update(Connection $connection): void
    {
        $connection->executeQuery('
            CREATE TABLE `plugin_translation` (
                `plugin_id`          BINARY(16)  NOT NULL,
                `language_id`        BINARY(16)  NOT NULL,
                `label`              VARCHAR(255) COLLATE utf8mb4_unicode_ci NULL,
                `description`        LONGTEXT COLLATE utf8mb4_unicode_ci NULL,
                `manufacturer_link`  text COLLATE utf8mb4_unicode_ci NULL,
                `support_link`       text COLLATE utf8mb4_unicode_ci NULL,
                `changelog`          JSON        NULL,
                `attributes`         JSON        NULL,
                `created_at`         DATETIME(3) NOT NULL,
                `updated_at`         DATETIME(3) NULL,
                PRIMARY KEY (`plugin_id`, `language_id`),
                CONSTRAINT `JSON.changelog` CHECK (JSON_VALID(`changelog`)),
                CONSTRAINT `JSON.attributes` CHECK (JSON_VALID(`attributes`)),
                CONSTRAINT `fk.plugin_translation.plugin_id` FOREIGN KEY (`plugin_id`) 
                  REFERENCES `plugin` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `fk.plugin_translation.language_id` FOREIGN KEY (`language_id`) 
                  REFERENCES `language` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            )  ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
