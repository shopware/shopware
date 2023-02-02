<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_4;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1610448012LandingPage extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1610448012;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            CREATE TABLE IF NOT EXISTS `landing_page` (
              `id` BINARY(16) NOT NULL,
              `version_id` BINARY(16) NOT NULL,
              `active` TINYINT(1) NOT NULL DEFAULT 1,
              `cms_page_id` BINARY(16) NULL,
              `created_at` DATETIME(3) NOT NULL,
              `updated_at` DATETIME(3) NULL,
              PRIMARY KEY (`id`, `version_id`),
              CONSTRAINT `fk.landing_page.cms_page_id` FOREIGN KEY (`cms_page_id`)
                REFERENCES `cms_page` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
            ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');

        $connection->executeStatement('
            CREATE TABLE IF NOT EXISTS `landing_page_translation` (
              `landing_page_id` BINARY(16) NOT NULL,
              `landing_page_version_id` BINARY(16) NOT NULL,
              `language_id` BINARY(16) NOT NULL,
              `name` VARCHAR(255) NULL,
              `meta_title` varchar(255) NULL,
              `meta_description` varchar(255) NULL,
              `keywords` varchar(255) NULL,
              `custom_fields` JSON NULL,
              `slot_config` JSON,
              `created_at` DATETIME(3) NOT NULL,
              `updated_at` DATETIME(3) NULL,
              PRIMARY KEY (`landing_page_id`, `landing_page_version_id`, `language_id`),
              CONSTRAINT `json.landing_page_translation.slot_config` CHECK (JSON_VALID(`slot_config`)),
              CONSTRAINT `json.landing_page_translation.custom_fields` CHECK (JSON_VALID(`custom_fields`)),
              CONSTRAINT `fk.landing_page_translation.language_id` FOREIGN KEY (`language_id`)
                REFERENCES `language` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
              CONSTRAINT `fk.landing_page_translation.landing_page_id` FOREIGN KEY (`landing_page_id`, `landing_page_version_id`)
                REFERENCES `landing_page` (`id`, `version_id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');

        $connection->executeStatement('
            CREATE TABLE IF NOT EXISTS `landing_page_tag` (
              `landing_page_id` BINARY(16) NOT NULL,
              `landing_page_version_id` BINARY(16) NOT NULL,
              `tag_id` BINARY(16) NOT NULL,
              PRIMARY KEY (`landing_page_id`, `landing_page_version_id`, `tag_id`),
              CONSTRAINT `fk.landing_page_tag.landing_page_version_id__landing_page_id` FOREIGN KEY (`landing_page_id`, `landing_page_version_id`)
                REFERENCES `landing_page` (`id`, `version_id`) ON DELETE CASCADE ON UPDATE CASCADE,
              CONSTRAINT `fk.landing_page_tag.tag_id` FOREIGN KEY (`tag_id`)
                REFERENCES `tag` (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');

        $connection->executeStatement('
            CREATE TABLE IF NOT EXISTS `landing_page_sales_channel` (
              `landing_page_id` BINARY(16) NOT NULL,
              `landing_page_version_id` BINARY(16) NOT NULL,
              `sales_channel_id` BINARY(16) NOT NULL,
              PRIMARY KEY (`landing_page_id`, `landing_page_version_id`, `sales_channel_id`),
              CONSTRAINT `fk.landing_page_sales_channel.product_id` FOREIGN KEY (`landing_page_id`, `landing_page_version_id`)
                REFERENCES `landing_page` (`id`, `version_id`) ON DELETE CASCADE ON UPDATE CASCADE,
              CONSTRAINT `fk.landing_page_sales_channel.sales_channel_id` FOREIGN KEY (`sales_channel_id`)
                REFERENCES `sales_channel` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
