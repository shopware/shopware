<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_6;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1736319636AddTableDocumentMedia extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1736319636;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            CREATE TABLE IF NOT EXISTS `document_media` (
                `id` BINARY(16) NOT NULL,
                `document_id` BINARY(16) NOT NULL,
                `media_id` BINARY(16) NOT NULL,
                `file_extension` VARCHAR(255) NOT NULL,
                `custom_fields` JSON NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3),
                PRIMARY KEY (`id`, `document_id`, `media_id`),
                CONSTRAINT `fk.document_media.document_id` FOREIGN KEY (`document_id`)
                    REFERENCES `document` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `fk.document_media.media_id` FOREIGN KEY (`media_id`)
                    REFERENCES `media` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }
}
