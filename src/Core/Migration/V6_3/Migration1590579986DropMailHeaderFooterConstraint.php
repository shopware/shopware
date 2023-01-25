<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1590579986DropMailHeaderFooterConstraint extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1590579986;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            ALTER TABLE `sales_channel`
            DROP FOREIGN KEY `fk.sales_channel.header_footer_id`;

            ALTER TABLE `sales_channel`
            ADD CONSTRAINT `fk.sales_channel.header_footer_id`
            FOREIGN KEY (`mail_header_footer_id`) REFERENCES `mail_header_footer` (`id`) ON UPDATE CASCADE;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
