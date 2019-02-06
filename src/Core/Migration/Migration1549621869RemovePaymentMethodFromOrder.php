<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1549621869RemovePaymentMethodFromOrder extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1549621869;
    }

    public function update(Connection $connection): void
    {
        $connection->exec('ALTER TABLE `order` MODIFY `payment_method_id` binary(16) NULL ;');
    }

    public function updateDestructive(Connection $connection): void
    {
        $connection->exec('ALTER TABLE `order` DROP FOREIGN KEY `fk.order.payment_method_id`;');
        $connection->exec('ALTER TABLE `order` DROP COLUMN `payment_method_id`;');
    }
}
