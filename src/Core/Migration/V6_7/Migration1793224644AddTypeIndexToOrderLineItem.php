<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Util\Database\TableHelper;

/**
 * @internal
 */
#[Package('checkout')]
class Migration1793224644AddTypeIndexToOrderLineItem extends MigrationStep
{
    private const INDEX_NAME = 'idx.order_line_item.type';

    public function getCreationTimestamp(): int
    {
        return 1793224644;
    }

    public function update(Connection $connection): void
    {
        if (TableHelper::indexExists($connection, 'order_line_item', self::INDEX_NAME)) {
            return;
        }

        $this->executeDdlStatement($connection, 'CREATE INDEX `' . self::INDEX_NAME . '` ON `order_line_item` (`type`)');
    }
}
