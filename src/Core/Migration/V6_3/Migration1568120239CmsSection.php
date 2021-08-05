<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1568120239CmsSection extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1568120239;
    }

    public function update(Connection $connection): void
    {
        $sql = <<<'SQL'
            CREATE TABLE `cms_section` (
              `id` BINARY(16) NOT NULL,
              `cms_page_id` BINARY(16) NOT NULL,
              `position` INT(11) NOT NULL,
              `type` VARCHAR(255) NOT NULL DEFAULT 'default',
              `name`  VARCHAR(255) NULL,
              `locked` tinyint(1) NOT NULL DEFAULT '0',
              `sizing_mode` VARCHAR(255) NOT NULL DEFAULT 'boxed',
              `mobile_behavior` VARCHAR(255) NOT NULL DEFAULT 'wrap',
              `background_color` VARCHAR(255) NULL,
              `background_media_id` BINARY(16) NULL,
              `background_media_mode` VARCHAR(255) NULL,
              `css_class` VARCHAR(255) NULL,
              `custom_fields` JSON NULL,
              `created_at` DATETIME(3) NOT NULL,
              `updated_at` DATETIME(3) NULL,
              PRIMARY KEY (`id`),
              CONSTRAINT `fk.cms_section.cms_page_id` FOREIGN KEY (`cms_page_id`)
                REFERENCES `cms_page` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
              CONSTRAINT `fk.cms_section.background_media_id` FOREIGN KEY (`background_media_id`)
                REFERENCES `media` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
              CONSTRAINT `json.cms_section.custom_fields` CHECK (JSON_VALID(`custom_fields`))
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;

        $connection->executeUpdate($sql);
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
