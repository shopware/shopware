<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('framework')]
class Migration1752750171AddIndexToOrderAddressCreateAndUpdate extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1752750171;
    }

    public function update(Connection $connection): void
    {
        if (!$this->indexExists($connection, 'order_address', 'idx.order_address_created_updated')) {
            $connection->executeStatement('CREATE INDEX `idx.order_address_created_updated` ON `order_address` (`created_at`, `updated_at`)');
        }
    }
}
