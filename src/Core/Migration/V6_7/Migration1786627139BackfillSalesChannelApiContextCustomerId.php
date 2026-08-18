<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('framework')]
class Migration1786627139BackfillSalesChannelApiContextCustomerId extends MigrationStep
{
    private const UPDATE_LIMIT = 1000;

    public function getCreationTimestamp(): int
    {
        return 1786627139;
    }

    public function update(Connection $connection): void
    {
        $limit = $this->getUpdateLimit();

        do {
            $tokens = $connection->fetchFirstColumn(
                <<<'SQL'
                    SELECT `token`
                    FROM `sales_channel_api_context`
                    WHERE `customer_id` IS NOT NULL
                      AND JSON_VALID(`payload`)
                      AND (
                        JSON_EXTRACT(`payload`, '$.customerId') IS NULL
                        OR JSON_UNQUOTE(JSON_EXTRACT(`payload`, '$.customerId')) = ''
                        OR JSON_UNQUOTE(JSON_EXTRACT(`payload`, '$.customerId')) != LOWER(HEX(`customer_id`))
                      )
                    LIMIT :limit
                SQL,
                ['limit' => $limit],
                ['limit' => ParameterType::INTEGER]
            );

            if ($tokens === []) {
                break;
            }

            $connection->executeStatement(
                <<<'SQL'
                    UPDATE `sales_channel_api_context`
                    SET `payload` = JSON_SET(
                        IF(JSON_TYPE(`payload`) = 'ARRAY', JSON_OBJECT(), `payload`),
                        '$.customerId',
                        LOWER(HEX(`customer_id`))
                    )
                    WHERE `token` IN (:tokens)
                SQL,
                ['tokens' => $tokens],
                ['tokens' => ArrayParameterType::STRING]
            );
        } while (\count($tokens) === $limit);
    }

    protected function getUpdateLimit(): int
    {
        return self::UPDATE_LIMIT;
    }
}
