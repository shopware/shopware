<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('framework')]
class Migration1795517143AddContentLayoutMediaUsage extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1795517143;
    }

    public function update(Connection $connection): void
    {
        // Maps the media of content layouts, so a replaced media can be traced to the layouts showing it with a single indexed query.
        // Deleting the layout or the media removes the rows.
        $connection->executeStatement('
            CREATE TABLE IF NOT EXISTS `content_layout_media_usage` (
                `content_layout_id` BINARY(16) NOT NULL,
                `media_id`          BINARY(16) NOT NULL,
                PRIMARY KEY (`content_layout_id`, `media_id`),
                CONSTRAINT `fk.content_layout_media_usage.content_layout_id` FOREIGN KEY (`content_layout_id`)
                    REFERENCES `content_layout` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `fk.content_layout_media_usage.media_id` FOREIGN KEY (`media_id`)
                    REFERENCES `media` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');

        $this->registerIndexer($connection, 'content_layout_media_usage.indexer');
    }
}
