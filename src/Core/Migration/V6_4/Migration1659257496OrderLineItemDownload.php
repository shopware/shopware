<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_4;

use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Product\State;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1659257496OrderLineItemDownload extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1659257496;
    }

    public function update(Connection $connection): void
    {
        if (!EntityDefinitionQueryHelper::columnExists($connection, 'order_line_item', 'states')) {
            $connection->executeStatement('
                ALTER TABLE `order_line_item`
                ADD COLUMN `states` JSON NULL AFTER `promotion_id`,
                ADD CONSTRAINT `json.order_line_item.states` CHECK (JSON_VALID(`states`))
            ');
            $connection->executeStatement('
                UPDATE `order_line_item`
                SET `states` = :states
                WHERE `states` IS NULL
            ', ['states' => json_encode([State::IS_PHYSICAL])]);
        }

        $connection->executeUpdate('
            CREATE TABLE IF NOT EXISTS `order_line_item_download` (
              `id` BINARY(16) NOT NULL,
              `version_id` BINARY(16) NOT NULL,
              `position` INT(11) NOT NULL DEFAULT 1,
              `access_granted` TINYINT(1) NOT NULL DEFAULT 0,
              `order_line_item_id` BINARY(16) NOT NULL,
              `order_line_item_version_id` BINARY(16) NOT NULL,
              `media_id` BINARY(16) NOT NULL,
              `custom_fields` JSON NULL,
              `created_at` DATETIME(3) NOT NULL,
              `updated_at` DATETIME(3) NULL,
              PRIMARY KEY (`id`, `version_id`),
              CONSTRAINT `json.order_line_item_download.custom_fields` CHECK (JSON_VALID(`custom_fields`)),
              CONSTRAINT `fk.order_line_item_download.media_id` FOREIGN KEY (`media_id`)
                REFERENCES `media` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
              CONSTRAINT `fk.order_line_item_download.order_line_item_id` FOREIGN KEY (`order_line_item_id`, `order_line_item_version_id`)
                REFERENCES `order_line_item` (`id`, `version_id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
