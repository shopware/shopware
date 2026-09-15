<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Util\Database\TableHelper;

/**
 * @internal
 */
#[Package('framework')]
class Migration1783617452AddWebhookDeliveryWebhookStatusIdIndex extends MigrationStep
{
    private const INDEX = 'idx.webhook_delivery.webhook_status_id';

    public function getCreationTimestamp(): int
    {
        return 1783617452;
    }

    public function update(Connection $connection): void
    {
        if (TableHelper::indexExists($connection, 'webhook_delivery', self::INDEX)) {
            return;
        }

        $connection->executeStatement(
            'CREATE INDEX `idx.webhook_delivery.webhook_status_id`
                ON `webhook_delivery` (`webhook_id`, `delivery_status`, `id`)'
        );
    }
}
