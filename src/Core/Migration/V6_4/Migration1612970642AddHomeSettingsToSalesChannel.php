<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_4;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1612970642AddHomeSettingsToSalesChannel extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1612970642;
    }

    public function update(Connection $connection): void
    {
        $sql = <<<'SQL'
ALTER TABLE `sales_channel_translation`
    ADD COLUMN `home_slot_config`       JSON                               AFTER `name`,
    ADD COLUMN `home_enabled`           TINYINT        NOT NULL DEFAULT 1  AFTER `name`,
    ADD COLUMN `home_name`              VARCHAR(255)   NULL                AFTER `name`,
    ADD COLUMN `home_meta_title`        VARCHAR(255)   NULL                AFTER `name`,
    ADD COLUMN `home_meta_description`  VARCHAR(255)   NULL                AFTER `name`,
    ADD COLUMN `home_keywords`          VARCHAR(255)   NULL                AFTER `name`,
    ADD CONSTRAINT `json.sales_channel_translation.home_slot_config`
            CHECK (JSON_VALID(`home_slot_config`))
SQL;
        $connection->executeStatement($sql);

        $sql = <<<'SQL'
ALTER TABLE `sales_channel`
    ADD COLUMN `home_cms_page_id` BINARY(16)     NULL                AFTER `navigation_category_depth`,
    ADD CONSTRAINT `fk.sales_channel.home_cms_page_id`
            FOREIGN KEY (`home_cms_page_id`)
            REFERENCES `cms_page` (`id`)
            ON DELETE RESTRICT
            ON UPDATE CASCADE;
SQL;
        $connection->executeStatement($sql);
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
