<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('framework')]
class Migration1752750086AddIndexToOrderLineItemCreateAndUpdate extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1752750086;
    }

    public function update(Connection $connection): void
    {
        if (!$this->indexExists($connection, 'order_line_item', 'idx.order_line_item_created_updated')) {
            $connection->executeStatement('CREATE INDEX `idx.order_line_item_created_updated` ON `order_line_item` (`created_at`, `updated_at`)');
        }
    }
}
