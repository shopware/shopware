<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1571210820AddPaymentMethodIdsToSalesChannel extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1571210820;
    }

    public function update(Connection $connection): void
    {
        $connection->exec('
            ALTER TABLE `sales_channel`
            ADD COLUMN `payment_method_ids` JSON NULL AFTER `mail_header_footer_id`,
            ADD CONSTRAINT `json.sales_channel.payment_method_ids` CHECK (JSON_VALID(`payment_method_ids`));
        ');

        $this->registerIndexer($connection, 'Swag.ManyToManyIdFieldIndexer');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
