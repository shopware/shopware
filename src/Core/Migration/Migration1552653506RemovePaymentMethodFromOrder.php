<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1552653506RemovePaymentMethodFromOrder extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1552653506;
    }

    public function update(Connection $connection): void
    {
        $connection->executeQuery('
            ALTER TABLE `order`
            DROP FOREIGN KEY `fk.order.payment_method_id`;
        ');
        $connection->executeQuery('
            ALTER TABLE `order`
            DROP COLUMN `payment_method_id`;
        ');
    }

    /**
     * update destructive changes
     */
    public function updateDestructive(Connection $connection): void
    {
        // nth
    }
}
