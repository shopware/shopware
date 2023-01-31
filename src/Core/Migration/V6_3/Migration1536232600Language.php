<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1536232600Language extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536232600;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            CREATE TABLE `language` (
              `id`                  BINARY(16)                              NOT NULL,
              `name`                VARCHAR(50) COLLATE utf8mb4_unicode_ci  NOT NULL,
              `parent_id`           BINARY(16)                              NULL,
              `locale_id`           BINARY(16)                              NOT NULL,
              `translation_code_id` BINARY(16)                              NULL,
              `custom_fields`       JSON                                    NULL,
              `created_at`          DATETIME(3)                             NOT NULL,
              `updated_at`          DATETIME(3)                             NULL,
              PRIMARY KEY (`id`),
              UNIQUE `uniq.translation_code_id` (translation_code_id),
              KEY `idx.language.translation_code_id` (`translation_code_id`),
              KEY `idx.language.language_id_parent_language_id` (`id`, `parent_id`),
              CONSTRAINT `json.language.custom_fields` CHECK (JSON_VALID(`custom_fields`)),
              CONSTRAINT `fk.language.parent_id` FOREIGN KEY (`parent_id`)
                REFERENCES `language` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
