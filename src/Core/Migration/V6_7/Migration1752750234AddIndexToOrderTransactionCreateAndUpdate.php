<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('framework')]
class Migration1752750234AddIndexToOrderTransactionCreateAndUpdate extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1752750234;
    }

    public function update(Connection $connection): void
    {
        if (!$this->indexExists($connection, 'order_transaction', 'idx.order_transaction_created_updated')) {
            $connection->executeStatement('CREATE INDEX `idx.order_transaction_created_updated` ON `order_transaction` (`created_at`, `updated_at`)');
        }
    }
}
