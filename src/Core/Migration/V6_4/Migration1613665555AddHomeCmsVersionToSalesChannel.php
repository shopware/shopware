<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_4;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1613665555AddHomeCmsVersionToSalesChannel extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1613665555;
    }

    public function update(Connection $connection): void
    {
        $sql = <<<'SQL'
ALTER TABLE `sales_channel`
    ADD COLUMN `home_cms_page_version_id` BINARY(16)     NULL                AFTER `home_cms_page_id`,
    DROP FOREIGN KEY `fk.sales_channel.home_cms_page_id`;
SQL;
        $connection->executeStatement($sql);

        $sql = <<<'SQL'
ALTER TABLE `sales_channel`
    ADD CONSTRAINT `fk.sales_channel.home_cms_page`
            FOREIGN KEY (`home_cms_page_id`, `home_cms_page_version_id`)
            REFERENCES `cms_page` (`id`, `version_id`)
            ON DELETE RESTRICT
            ON UPDATE CASCADE;
SQL;
        $connection->executeStatement($sql);
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
