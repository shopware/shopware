<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_4;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1627993629FixCustomerIdInSalesChannelContext extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1627993629;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(
            'UPDATE sales_channel_api_context
                SET customer_id = UNHEX(JSON_UNQUOTE(JSON_EXTRACT(payload, \'$.customerId\')))
                WHERE customer_id IS NULL'
        );
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
