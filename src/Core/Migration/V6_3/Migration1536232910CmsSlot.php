<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1536232910CmsSlot extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536232910;
    }

    public function update(Connection $connection): void
    {
        $sql = <<<'SQL'
            CREATE TABLE `cms_slot` (
              `id` BINARY(16) NOT NULL,
              `version_id` BINARY(16) NOT NULL,
              `cms_block_id` BINARY(16) NOT NULL,
              `type` VARCHAR(255) NOT NULL,
              `slot` VARCHAR(255) NOT NULL,
              `locked` tinyint(1) NOT NULL DEFAULT '0',
              `created_at` DATETIME(3) NOT NULL,
              `updated_at` DATETIME(3) NULL,
              PRIMARY KEY (`id`, `version_id`),
              CONSTRAINT `fk.cms_slot.cms_block_id` FOREIGN KEY (`cms_block_id`)
                REFERENCES `cms_block` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;

        $connection->executeStatement($sql);

        $sql = <<<'SQL'
            CREATE TABLE `cms_slot_translation` (
              `cms_slot_id` BINARY(16) NOT NULL,
              `cms_slot_version_id` BINARY(16) NOT NULL,
              `language_id` BINARY(16) NOT NULL,
              `config` JSON NULL,
              `custom_fields` JSON NULL,
              `created_at` DATETIME(3) NOT NULL,
              `updated_at` DATETIME(3) NULL,
              PRIMARY KEY (`cms_slot_id`, `cms_slot_version_id`, `language_id`),
              CONSTRAINT `json.cms_slot_translation.config` CHECK(JSON_VALID(`config`)),
              CONSTRAINT `json.cms_slot_translation.custom_fields` CHECK (JSON_VALID(`custom_fields`)),
              CONSTRAINT `fk.cms_slot_translation.cms_slot_id` FOREIGN KEY (`cms_slot_id`, `cms_slot_version_id`)
                REFERENCES `cms_slot` (`id`, `version_id`) ON DELETE CASCADE ON UPDATE CASCADE,
              CONSTRAINT `fk.cms_slot_translation.language_id` FOREIGN KEY (`language_id`)
                REFERENCES `language` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;

        $connection->executeStatement($sql);
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
