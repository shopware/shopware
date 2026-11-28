<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('discovery')]
class Migration1795776130SetSalesChannelHreflangDefaultDomainNullOnDelete extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1795776130;
    }

    public function update(Connection $connection): void
    {
        // WHY: ON DELETE CASCADE deleted the whole sales channel together with its hreflang default domain.
        // SAFETY: The statements contain static identifiers only, and the foreign key is added again right after the drop.
        /** @phpstan-ignore shopware.dropStatement (FK is directly added again so dropping the FK is no issue for blue green) */
        $this->dropForeignKeyIfExists($connection, 'sales_channel', 'fk.sales_channel.hreflang_default_domain_id');

        $connection->executeStatement('
            ALTER TABLE `sales_channel`
            ADD CONSTRAINT `fk.sales_channel.hreflang_default_domain_id`
            FOREIGN KEY (`hreflang_default_domain_id`)
            REFERENCES `sales_channel_domain` (`id`)
            ON DELETE SET NULL ON UPDATE CASCADE;
        ');
    }
}
