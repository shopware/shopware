<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1578491480Hreflang extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1578491480;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('ALTER TABLE `sales_channel` ADD `hreflang_default_domain_id` BINARY(16) NULL AFTER `navigation_category_depth`;');

        $connection->executeStatement('
            ALTER TABLE `sales_channel`
            ADD CONSTRAINT `fk.sales_channel.hreflang_default_domain_id`
            FOREIGN KEY (`hreflang_default_domain_id`)
            REFERENCES `sales_channel_domain` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;
        ');

        $connection->executeStatement('ALTER TABLE `sales_channel` ADD `hreflang_active` tinyint(1) unsigned DEFAULT 0 AFTER `navigation_category_depth`;');

        $connection->executeStatement('ALTER TABLE `sales_channel_domain` ADD `hreflang_use_only_locale` tinyint(1) unsigned DEFAULT 0 AFTER `snippet_set_id`;');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
