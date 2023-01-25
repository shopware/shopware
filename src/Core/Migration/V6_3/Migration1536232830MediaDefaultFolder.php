<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1536232830MediaDefaultFolder extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536232830;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            CREATE TABLE `media_default_folder` (
              `id`                  BINARY(16)      NOT NULL,
              `association_fields`  JSON            NOT NULL,
              `entity`              VARCHAR(255)    NOT NULL,
              `custom_fields`       JSON            NULL,
              `created_at`          DATETIME(3)     NOT NULL,
              `updated_at`          DATETIME(3)     NULL,
              PRIMARY KEY (`id`),
              UNIQUE KEY `uniq.media_default_folder.entity` (`entity`),
              CONSTRAINT `json.media_default_folder.custom_fields` CHECK (JSON_VALID(`custom_fields`)),
              CONSTRAINT `json.media_default_folder.association_fields` CHECK (JSON_VALID(`association_fields`))
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // no destructive changes
    }
}
