<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1536232750Snippet extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536232750;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            CREATE TABLE `snippet` (
              `id`              BINARY(16)                              NOT NULL,
              `translation_key` VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
              `value`           LONGTEXT COLLATE utf8mb4_unicode_ci     NOT NULL,
              `author`          VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
              `snippet_set_id`  BINARY(16)                              NOT NULL,
              `custom_fields`   JSON                                    NULL,
              `created_at`      DATETIME(3)                             NOT NULL,
              `updated_at`      DATETIME(3)                             NULL,
              PRIMARY KEY (`id`),
              UNIQUE `uniq.snippet_set_id_translation_key` (`snippet_set_id`, `translation_key`),
              CONSTRAINT `json.snippet.custom_fields` CHECK (JSON_VALID(`custom_fields`)),
              CONSTRAINT `fk.snippet.snippet_set_id` FOREIGN KEY (`snippet_set_id`)
                REFERENCES `snippet_set` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
