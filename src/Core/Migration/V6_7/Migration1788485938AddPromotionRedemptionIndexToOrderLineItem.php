<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\DriverException;
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

    private const ER_TOO_LONG_KEY = 1071;

    public function getCreationTimestamp(): int
    {
        return 1788485938;
    }

    public function update(Connection $connection): void
    {
        // The recount reads all five columns; this covering index avoids the row lookups.
        if (TableHelper::indexExists($connection, 'order_line_item', self::INDEX_NAME)) {
            return;
        }

        try {
            $connection->executeStatement(
                'CREATE INDEX `' . self::INDEX_NAME . '` ON `order_line_item` '
                . '(`promotion_id`, `version_id`, `type`, `order_id`, `order_version_id`)'
            );
        } catch (DriverException $e) {
            if ($e->getCode() !== self::ER_TOO_LONG_KEY) {
                throw $e;
            }

            // `type` exceeds the key limit on older table formats and small page-size servers.
        }
    }
}
