<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Checkout\DocumentV2\Aggregate\DocumentFile\DocumentFileDefinition;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Util\Database\TableHelper;

/**
 * @internal
 */
#[Package('after-sales')]
class Migration1776770187AddDocumentFileTable extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1776770187;
    }

    public function update(Connection $connection): void
    {
        $table = DocumentFileDefinition::ENTITY_NAME;

        if (TableHelper::tableExists($connection, $table)) {
            return;
        }

        $connection->executeStatement(\sprintf('
            CREATE TABLE `%s` (
                `id` BINARY(16) NOT NULL,
                `document_id` BINARY(16) NOT NULL,
                `media_id` BINARY(16) NOT NULL,
                `document_format` VARCHAR(255) NOT NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,

                PRIMARY KEY (`id`),
                KEY `idx.document_file.document_id` (`document_id`),
                UNIQUE KEY `uniq.document_file.media_id` (`media_id`),
                UNIQUE KEY `uniq.document_file.document_id__document_format` (`document_id`, `document_format`),

                CONSTRAINT `fk.document_file.document_id`
                    FOREIGN KEY (`document_id`)
                    REFERENCES `document` (`id`)
                    ON DELETE CASCADE ON UPDATE CASCADE,

                CONSTRAINT `fk.document_file.media_id`
                    FOREIGN KEY (`media_id`)
                    REFERENCES `media` (`id`)
                    ON DELETE RESTRICT ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ', $table));
    }
}
