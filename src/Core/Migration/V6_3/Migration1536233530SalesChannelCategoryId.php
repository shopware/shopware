<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1536233530SalesChannelCategoryId extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536233530;
    }

    public function update(Connection $connection): void
    {
        $this->addCmsToCategory($connection);
        $this->addSlotConfigToCategory($connection);
    }

    public function updateDestructive(Connection $connection): void
    {
        $this->removeNavigationFromSalesChannel($connection);
        $this->dropNavigation($connection);
    }

    private function addCmsToCategory(Connection $connection): void
    {
        $sql = <<<'SQL'
ALTER TABLE `category`
ADD COLUMN `cms_page_id` BINARY(16) NULL AFTER `media_id`,
ADD CONSTRAINT `fk.category.cms_page_id` FOREIGN KEY (`cms_page_id`)
REFERENCES `cms_page` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
SQL;

        $connection->executeUpdate($sql);
    }

    private function removeNavigationFromSalesChannel(Connection $connection): void
    {
        $connection->executeUpdate('ALTER TABLE `sales_channel` DROP FOREIGN KEY `fk.sales_channel.navigation_id`');

        $sql = <<<'SQL'
ALTER TABLE `sales_channel`
    DROP COLUMN `navigation_id`,
    DROP COLUMN `navigation_version_id`
SQL;

        $connection->executeUpdate($sql);
    }

    private function dropNavigation(Connection $connection): void
    {
        $connection->executeUpdate('DROP TABLE `navigation_translation`');
        $connection->executeUpdate('DROP TABLE `navigation`');
    }

    private function addSlotConfigToCategory(Connection $connection): void
    {
        $sql = <<<'SQL'
ALTER TABLE `category_translation`
    ADD COLUMN `slot_config` JSON,
    ADD CONSTRAINT `json.category_translation.slot_config` CHECK (JSON_VALID(`slot_config`))
SQL;

        $connection->executeUpdate($sql);
    }
}
