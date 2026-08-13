<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('framework')]
class Migration1786627139BackfillSalesChannelApiContextCustomerId extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1786627139;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(
            <<<'SQL'
                UPDATE `sales_channel_api_context`
                SET `payload` = JSON_SET(
                    IF(JSON_TYPE(`payload`) = 'ARRAY', JSON_OBJECT(), `payload`),
                    '$.customerId',
                    LOWER(HEX(`customer_id`))
                )
                WHERE `customer_id` IS NOT NULL
                  AND JSON_VALID(`payload`)
                  AND (
                    JSON_EXTRACT(`payload`, '$.customerId') IS NULL
                    OR JSON_UNQUOTE(JSON_EXTRACT(`payload`, '$.customerId')) = ''
                  );
            SQL
        );
    }
}
