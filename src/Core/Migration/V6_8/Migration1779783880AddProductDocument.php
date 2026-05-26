<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_8;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('framework')]
class Migration1779783880AddProductDocument extends MigrationStep
{
    private const FOLDER_NAME = 'Product documents';

    public function getCreationTimestamp(): int
    {
        return 1779783880;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            CREATE TABLE IF NOT EXISTS `product_document` (
              `id` BINARY(16) NOT NULL,
              `version_id` BINARY(16) NOT NULL,
              `product_id` BINARY(16) NOT NULL,
              `product_version_id` BINARY(16) NOT NULL,
              `media_id` BINARY(16) NOT NULL,
              `title` VARCHAR(255) NULL,
              `position` INT(11) NOT NULL DEFAULT 0,
              `created_at` DATETIME(3) NOT NULL,
              `updated_at` DATETIME(3) NULL,
              PRIMARY KEY (`id`, `version_id`),
              UNIQUE KEY `uniq.product_document.product_media` (`product_id`, `product_version_id`, `media_id`),
              CONSTRAINT `fk.product_document.product_id` FOREIGN KEY (`product_id`, `product_version_id`)
                REFERENCES `product` (`id`, `version_id`) ON DELETE CASCADE ON UPDATE CASCADE,
              CONSTRAINT `fk.product_document.media_id` FOREIGN KEY (`media_id`)
                REFERENCES `media` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');

        $this->createMediaFolder($connection);
    }

    public function updateDestructive(Connection $connection): void
    {
    }

    private function createMediaFolder(Connection $connection): void
    {
        $createdAt = (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT);

        $defaultFolderId = $connection->fetchOne('SELECT id FROM media_default_folder WHERE entity = :entity', ['entity' => 'product_document']);
        if (!$defaultFolderId) {
            $defaultFolderId = Uuid::randomBytes();

            $connection->insert('media_default_folder', [
                'id' => $defaultFolderId,
                'entity' => 'product_document',
                'created_at' => $createdAt,
            ]);
        }

        $folderId = $connection->fetchOne('SELECT id FROM media_folder WHERE default_folder_id = :id', ['id' => $defaultFolderId]);
        if ($folderId) {
            return;
        }

        $configurationId = Uuid::randomBytes();
        $folderId = Uuid::randomBytes();

        $connection->executeStatement('
            INSERT INTO `media_folder_configuration` (`id`, `thumbnail_quality`, `create_thumbnails`, `private`, `created_at`)
            VALUES (:id, 80, 0, 1, :createdAt)
        ', [
            'id' => $configurationId,
            'createdAt' => $createdAt,
        ]);

        $connection->executeStatement('
            INSERT INTO `media_folder` (`id`, `name`, `default_folder_id`, `media_folder_configuration_id`, `use_parent_configuration`, `child_count`, `created_at`)
            VALUES (:folderId, :folderName, :defaultFolderId, :configurationId, 0, 0, :createdAt)
        ', [
            'folderId' => $folderId,
            'folderName' => self::FOLDER_NAME,
            'defaultFolderId' => $defaultFolderId,
            'configurationId' => $configurationId,
            'createdAt' => $createdAt,
        ]);
    }
}
