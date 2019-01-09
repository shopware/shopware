<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1548663166AddPluginTranslation extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1548663166;
    }

    public function update(Connection $connection): void
    {
        $connection->executeQuery('
CREATE TABLE `plugin_translation` (
    `plugin_id`          BINARY(16)  NOT NULL,
    `language_id`        BINARY(16)  NOT NULL,
    `language_parent_id` BINARY(16)  NULL,
    `label`              VARCHAR(255) COLLATE utf8mb4_unicode_ci,
    `description`        LONGTEXT COLLATE utf8mb4_unicode_ci,
    `manufacturer_link`  TEXT COLLATE utf8mb4_unicode_ci,
    `support_link`       TEXT COLLATE utf8mb4_unicode_ci,
    `changelog`          JSON        NULL,
    `created_at`         DATETIME(3) NOT NULL,
    `updated_at`         DATETIME(3) NULL,
    PRIMARY KEY (`plugin_id`, `language_id`),
    CONSTRAINT `fk.plugin_translation.plugin_id` FOREIGN KEY (`plugin_id`) REFERENCES `plugin` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk.plugin_translation.language_id` FOREIGN KEY (`language_id`, `language_parent_id`) REFERENCES `language` (`id`, `parent_id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk.plugin_translation.language_parent_id` FOREIGN KEY (`plugin_id`, `language_parent_id`) REFERENCES `plugin_translation` (`plugin_id`, `language_id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `json.changelog` CHECK (JSON_VALID(`changelog`))
)
    ENGINE = InnoDB
    DEFAULT CHARSET = utf8mb4
    COLLATE = utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
