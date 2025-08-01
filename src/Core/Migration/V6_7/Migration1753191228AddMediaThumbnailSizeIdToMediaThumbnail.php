<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('discovery')]
class Migration1753191228AddMediaThumbnailSizeIdToMediaThumbnail extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1753191228;
    }

    public function update(Connection $connection): void
    {
        if (!$this->columnExists($connection, 'media_thumbnail', 'media_thumbnail_size_id')) {
            $this->addColumn($connection, 'media_thumbnail', 'media_thumbnail_size_id', 'BINARY(16)');
        }

        $this->migrateMediaThumbnailRows($connection);

        if (!$this->indexExists($connection, 'media_thumbnail', 'fk.media_thumbnail.media_thumbnail_size_id')) {
            $connection->executeStatement('
                ALTER TABLE `media_thumbnail`
                ADD CONSTRAINT `fk.media_thumbnail.media_thumbnail_size_id`
                FOREIGN KEY (`media_thumbnail_size_id`)
                REFERENCES `media_thumbnail_size` (`id`)
                ON DELETE SET NULL ON UPDATE CASCADE
            ');
        }

        $this->registerIndexer($connection, 'media.indexer');
    }

    public function updateDestructive(Connection $connection): void
    {
        $this->migrateMediaThumbnailRows($connection);

        if ($this->indexExists($connection, 'media_thumbnail', 'fk.media_thumbnail.media_thumbnail_size_id')) {
            $connection->executeStatement('
                ALTER TABLE `media_thumbnail`
                DROP FOREIGN KEY `fk.media_thumbnail.media_thumbnail_size_id`
            ');
        }

        $connection->executeStatement('
            ALTER TABLE `media_thumbnail`
            MODIFY COLUMN `media_thumbnail_size_id` BINARY(16) NOT NULL
        ');

        $connection->executeStatement('
            ALTER TABLE `media_thumbnail`
            ADD CONSTRAINT `fk.media_thumbnail.media_thumbnail_size_id`
            FOREIGN KEY (`media_thumbnail_size_id`)
            REFERENCES `media_thumbnail_size` (`id`)
            ON DELETE RESTRICT ON UPDATE CASCADE
        ');

        $this->registerIndexer($connection, 'media.indexer');
    }

    private function migrateMediaThumbnailRows(Connection $connection): void
    {
        $batchSize = 10000;

        do {
            $affected = $connection->executeStatement('
                UPDATE `media_thumbnail`
                SET media_thumbnail_size_id = (
                    SELECT size.id
                    FROM `media_thumbnail_size` AS size
                    WHERE size.width = media_thumbnail.width
                    AND size.height = media_thumbnail.height
                    LIMIT 1
                )
                WHERE media_thumbnail_size_id IS NULL
                AND EXISTS (
                    SELECT 1
                    FROM `media_thumbnail_size` AS size
                    WHERE size.width = media_thumbnail.width
                    AND size.height = media_thumbnail.height
                )
                LIMIT ' . $batchSize);
        } while ($affected > 0);

        /** @var int */
        $invalidCount = $connection->fetchOne('
            SELECT COUNT(*)
            FROM `media_thumbnail`
            WHERE media_thumbnail_size_id IS NULL
        ');

        if (!$invalidCount) {
            return;
        }

        /** @var ?string */
        $fallbackId = $connection->fetchOne('SELECT id FROM media_thumbnail_size WHERE width = 0 AND height = 0');
        if (!$fallbackId) {
            $fallbackId = Uuid::randomBytes();

            $connection->executeStatement('
                INSERT INTO `media_thumbnail_size` (`id`, `width`, `height`, `created_at`)
                VALUES (:id, 0, 0, NOW())
            ', ['id' => $fallbackId]);
        }

        do {
            $affected = $connection->executeStatement('
                UPDATE `media_thumbnail`
                SET media_thumbnail_size_id = :id
                WHERE media_thumbnail_size_id IS NULL
                LIMIT ' . $batchSize, [
                'id' => $fallbackId,
            ]);
        } while ($affected > 0);
    }
}
