<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('inventory')]
class Migration1763125902AddOrderLineItemProductTypePayload extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1763125902;
    }

    public function update(Connection $connection): void
    {
        $batchSize = 5000;

        do {
            $affected = $connection->executeStatement(
                '
                UPDATE `order_line_item`
                    INNER JOIN `order_line_item_download`
                        ON order_line_item_download.order_line_item_id = order_line_item.id AND order_line_item_download.order_line_item_version_id = order_line_item.version_id
                 SET payload = JSON_SET(
                    payload,
                    \'$.productType\',
                    \'digital\'
                 )
                 WHERE order_line_item.type = \'product\' OR order_line_item.type = \'custom\' LIMIT ' . $batchSize
            );
        } while ($affected > 0);

        do {
            $affected = $connection->executeStatement(
                '
                UPDATE `order_line_item`
                SET payload = JSON_SET(
                    payload,
                    \'$.productType\',
                    \'physical\'
                )
                WHERE (type = \'product\' OR type = \'custom\')
                   AND states IS NOT NULL
                   AND JSON_CONTAINS(states, \'"is-physical"\') LIMIT ' . $batchSize
            );
        } while ($affected > 0);
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
