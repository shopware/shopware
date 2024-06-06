<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_5;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1675082889DropUnusedTables extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1675082889;
    }

    public function update(Connection $connection): void
    {
        $this->dropTableIfExists($connection, 'message_queue_stats');
        $this->dropTableIfExists($connection, 'mail_template_sales_channel');
        $this->dropTableIfExists($connection, 'sales_channel_rule');

        $this->dropColumnIfExists($connection, 'customer_address', 'vat_id');

        $this->dropColumnIfExists($connection, 'customer', 'newsletter');

        $this->dropColumnIfExists($connection, 'product', 'whitelist_ids');

        $this->dropColumnIfExists($connection, 'product', 'blacklist_ids');

        $this->removeTrigger($connection, 'customer_address_vat_id_insert');
        $this->removeTrigger($connection, 'customer_address_vat_id_update');
        $this->removeTrigger($connection, 'order_cash_rounding_insert');
    }
}
