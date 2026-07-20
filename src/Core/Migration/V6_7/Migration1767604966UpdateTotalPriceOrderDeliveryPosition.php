<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Types;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Util\Database\TableHelper;

/**
 * @internal
 */
#[Package('checkout')]
class Migration1767604966UpdateTotalPriceOrderDeliveryPosition extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1767604966;
    }

    public function update(Connection $connection): void
    {
        $columnType = TableHelper::getColumnOfTable($connection, 'order_delivery_position', 'total_price')->type;
        if ($columnType === Types::INTEGER) {
            $connection->executeStatement('
                ALTER TABLE `order_delivery_position`
                MODIFY `total_price` DOUBLE
                GENERATED ALWAYS AS (JSON_UNQUOTE(JSON_EXTRACT(`price`, \'$.totalPrice\'))) VIRTUAL
            ');
        }
    }
}
