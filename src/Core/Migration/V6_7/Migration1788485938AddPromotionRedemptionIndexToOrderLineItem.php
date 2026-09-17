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
class Migration1788485938AddPromotionRedemptionIndexToOrderLineItem extends MigrationStep
{
    private const INDEX_NAME = 'idx.order_line_item.promotion_redemption';

    private const FK_INDEX_NAME = 'fk.order_line_item.promotion_id';

    public function getCreationTimestamp(): int
    {
        return 1788485938;
    }

    public function update(Connection $connection): void
    {
        // The recount reads all four columns; this covering index avoids the row lookups.
        if (TableHelper::indexExists($connection, 'order_line_item', self::INDEX_NAME)) {
            return;
        }

        $connection->executeStatement(
            'CREATE INDEX `' . self::INDEX_NAME . '` ON `order_line_item` '
            . '(`promotion_id`, `version_id`, `order_id`, `order_version_id`)'
        );
    }

    public function updateDestructive(Connection $connection): void
    {
        // The covering index leads with `promotion_id`, so it can serve the foreign key alone.
        // InnoDB retires the index it created for the constraint itself, leaving nothing to drop
        // here; a schema carrying that index explicitly, as a restored dump does, keeps both and
        // maintains both on every line item write.
        if (!TableHelper::indexExists($connection, 'order_line_item', self::INDEX_NAME)) {
            return;
        }

        if (!TableHelper::indexExists($connection, 'order_line_item', self::FK_INDEX_NAME)) {
            return;
        }

        $this->executeDdlStatement(
            $connection,
            'DROP INDEX `' . self::FK_INDEX_NAME . '` ON `order_line_item`'
        );
    }
}
