<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1536232900CmsBlock extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536232900;
    }

    public function update(Connection $connection): void
    {
        $sql = <<<'SQL'
            CREATE TABLE `cms_block` (
              `id` BINARY(16) NOT NULL,
              `cms_page_id` BINARY(16) NOT NULL,
              `position` INT(11) NOT NULL,
              `type` VARCHAR(255) NOT NULL,
              `name`  VARCHAR(255) NULL,
              `locked` tinyint(1) NOT NULL DEFAULT '0',
              `sizing_mode` VARCHAR(255) NULL,
              `margin_top` VARCHAR(255) NULL,
              `margin_bottom` VARCHAR(255) NULL,
              `margin_left` VARCHAR(255) NULL,
              `margin_right` VARCHAR(255) NULL,
              `background_color` VARCHAR(255) NULL,
              `background_media_id` BINARY(16) NULL,
              `background_media_mode` VARCHAR(255) NULL,
              `css_class` VARCHAR(255) NULL,
              `custom_fields` JSON NULL,
              `created_at` DATETIME(3) NOT NULL,
              `updated_at` DATETIME(3) NULL,
              PRIMARY KEY (`id`),
              CONSTRAINT `fk.cms_block.cms_page_id` FOREIGN KEY (`cms_page_id`)
                REFERENCES `cms_page` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
              CONSTRAINT `fk.cms_block.background_media_id` FOREIGN KEY (`background_media_id`)
                REFERENCES `media` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
              CONSTRAINT `json.cms_block.custom_fields` CHECK (JSON_VALID(`custom_fields`))
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;

        $connection->executeStatement($sql);
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
