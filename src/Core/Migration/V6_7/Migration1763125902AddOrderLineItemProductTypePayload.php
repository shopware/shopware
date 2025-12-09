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
        $connection->executeStatement(<<<'SQL'
            UPDATE `order_line_item` oli
                INNER JOIN `order_line_item_download` olid
                    ON olid.order_line_item_id = oli.id AND olid.order_line_item_version_id = oli.version_id
             SET payload = JSON_SET(
                payload,
                '$.productType',
                'digital'
             )
             WHERE oli.type = 'product' OR oli.type = 'custom'
            SQL);

        $connection->executeStatement(<<<'SQL'
            UPDATE `order_line_item`
             SET payload = JSON_SET(
                payload,
                '$.productType',
                'physical'
             )
             WHERE (type = 'product' OR type = 'custom')
               AND states IS NOT NULL
               AND JSON_CONTAINS(states, '"is-physical"')
            SQL);
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
