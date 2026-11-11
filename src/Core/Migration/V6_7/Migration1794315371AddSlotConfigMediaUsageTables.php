<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('discovery')]
class Migration1794315371AddSlotConfigMediaUsageTables extends MigrationStep
{
    /**
     * translation table => entity the translation belongs to
     */
    private const OVERRIDES = [
        'category_translation' => 'category',
        'product_translation' => 'product',
        'landing_page_translation' => 'landing_page',
    ];

    public function getCreationTimestamp(): int
    {
        return 1794315371;
    }

    public function update(Connection $connection): void
    {
        // Maps the media of the layouts and of the layout overrides, so a replaced media can be traced to the pages showing it with a single indexed query.
        // Deleting the layout, the translation or the media removes the rows.
        $connection->executeStatement('
            CREATE TABLE IF NOT EXISTS `cms_page_media_usage` (
                `cms_page_id`         BINARY(16) NOT NULL,
                `cms_page_version_id` BINARY(16) NOT NULL,
                `media_id`            BINARY(16) NOT NULL,
                PRIMARY KEY (`cms_page_id`, `cms_page_version_id`, `media_id`),
                CONSTRAINT `fk.cms_page_media_usage.cms_page_id` FOREIGN KEY (`cms_page_id`, `cms_page_version_id`)
                    REFERENCES `cms_page` (`id`, `version_id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `fk.cms_page_media_usage.media_id` FOREIGN KEY (`media_id`)
                    REFERENCES `media` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
        $this->registerIndexer($connection, 'cms_page_media_usage.indexer');

        // the layout overrides of categories, products and landing pages
        foreach (self::OVERRIDES as $translation => $entity) {
            $connection->executeStatement(\sprintf('
                CREATE TABLE IF NOT EXISTS `%1$s_media_usage` (
                    `%2$s_id`         BINARY(16) NOT NULL,
                    `%2$s_version_id` BINARY(16) NOT NULL,
                    `language_id`     BINARY(16) NOT NULL,
                    `media_id`        BINARY(16) NOT NULL,
                    PRIMARY KEY (`%2$s_id`, `%2$s_version_id`, `language_id`, `media_id`),
                    CONSTRAINT `fk.%1$s_media_usage.translation` FOREIGN KEY (`%2$s_id`, `%2$s_version_id`, `language_id`)
                        REFERENCES `%1$s` (`%2$s_id`, `%2$s_version_id`, `language_id`) ON DELETE CASCADE ON UPDATE CASCADE,
                    CONSTRAINT `fk.%1$s_media_usage.media_id` FOREIGN KEY (`media_id`)
                        REFERENCES `media` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            ', $translation, $entity));

            $this->registerIndexer($connection, $translation . '_media_usage.indexer');
        }
    }
}
