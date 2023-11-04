<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1536232770VersionCommitData extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536232770;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            CREATE TABLE `version_commit_data` (
              `id`                  BINARY(16)                              NOT NULL,
              `auto_increment`      BIGINT NOT                              NULL AUTO_INCREMENT UNIQUE,
              `version_commit_id`   BINARY(16)                              NOT NULL,
              `entity_name`         VARCHAR(100) COLLATE utf8mb4_unicode_ci NOT NULL,
              `entity_id`           JSON                                    NOT NULL,
              `action`              VARCHAR(100) COLLATE utf8mb4_unicode_ci NOT NULL,
              `payload`             JSON                                    NOT NULL,
              `user_id`             BINARY(16)                              NULL,
              `integration_id`      BINARY(16)                              NULL,
              `created_at`          DATETIME(3)                             NOT NULL,
              `updated_at`          DATETIME(3)                             NULL,
              PRIMARY KEY (`id`),
              CONSTRAINT `json.version_commit_data.entity_id` CHECK (JSON_VALID(`entity_id`)),
              CONSTRAINT `json.version_commit_data.payload` CHECK (JSON_VALID(`payload`)),
              CONSTRAINT `fk.version_commit_data.version_commit_id` FOREIGN KEY  (`version_commit_id`)
                REFERENCES `version_commit` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
