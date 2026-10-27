<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * Existing layouts need no backfill: a layout without a publication row gets its first revision from its
 * `layout` column on first access, see `LayoutRevisionService::reconcile()`.
 *
 * @internal
 */
#[Package('framework')]
class Migration1793099454AddContentLayoutRevisionTables extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1793099454;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            CREATE TABLE IF NOT EXISTS `content_layout_revision` (
                `id` BINARY(16) NOT NULL,
                `content_layout_id` BINARY(16) NOT NULL,
                `parent_id` BINARY(16) NULL,
                `layout` JSON NOT NULL,
                `created_by_id` BINARY(16) NULL,
                `published_at` DATETIME(3) NULL,
                `created_at` DATETIME(3) NOT NULL,
                PRIMARY KEY (`id`),
                KEY `idx.content_layout_revision.content_layout_id` (`content_layout_id`, `created_at`),
                CONSTRAINT `json.content_layout_revision.layout` CHECK (JSON_VALID(`layout`)),
                CONSTRAINT `fk.content_layout_revision.content_layout_id`
                    FOREIGN KEY (`content_layout_id`) REFERENCES `content_layout` (`id`)
                    ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');

        $connection->executeStatement('
            CREATE TABLE IF NOT EXISTS `content_layout_branch` (
                `id` BINARY(16) NOT NULL,
                `content_layout_id` BINARY(16) NOT NULL,
                `name` VARCHAR(255) NOT NULL,
                `base_revision_id` BINARY(16) NOT NULL,
                `head_revision_id` BINARY(16) NOT NULL,
                `live_revision_id` BINARY(16) NOT NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NOT NULL,
                PRIMARY KEY (`id`),
                KEY `fk.content_layout_branch.content_layout_id` (`content_layout_id`),
                CONSTRAINT `fk.content_layout_branch.content_layout_id`
                    FOREIGN KEY (`content_layout_id`) REFERENCES `content_layout` (`id`)
                    ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');

        $connection->executeStatement('
            CREATE TABLE IF NOT EXISTS `content_layout_publication` (
                `content_layout_id` BINARY(16) NOT NULL,
                `revision_id` BINARY(16) NOT NULL,
                `layout_checksum` CHAR(64) NOT NULL,
                PRIMARY KEY (`content_layout_id`),
                CONSTRAINT `fk.content_layout_publication.content_layout_id`
                    FOREIGN KEY (`content_layout_id`) REFERENCES `content_layout` (`id`)
                    ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // The JSON column of an earlier draft of this feature, which never shipped.
        $this->dropColumnIfExists($connection, 'content_layout', 'revisions');
    }
}
