<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1720610755RemoveDefaultPaymentMethodFromCustomer extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1720610755;
    }

    public function update(Connection $connection): void
    {
        if ($this->columnExists($connection, 'customer', 'default_payment_method_id')) {
            $connection->executeStatement('ALTER TABLE `customer` MODIFY COLUMN `default_payment_method_id` BINARY(16) NULL');
        }
    }

    public function updateDestructive(Connection $connection): void
    {
        if ($this->columnExists($connection, 'customer', 'default_payment_method_id')) {
            $this->dropForeignKeyIfExists($connection, 'customer', 'fk.customer.default_payment_method_id');
            $this->dropColumnIfExists($connection, 'customer', 'default_payment_method_id');
        }
    }
}
