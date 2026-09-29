<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('after-sales')]
class Migration1781508123UpdateLineItemPurchasePriceRuleConditions extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1781508123;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(
            <<<'SQL'
                UPDATE `rule_condition`
                SET `value` = JSON_SET(
                    JSON_REMOVE(`value`, '$.isNet'),
                    '$.type',
                    IF(JSON_UNQUOTE(JSON_EXTRACT(`value`, '$.isNet')) = 'true', 'net', 'gross')
                )
                WHERE `type` = 'cartLineItemPurchasePrice'
                  AND JSON_VALID(`value`)
                  AND JSON_EXTRACT(`value`, '$.isNet') IS NOT NULL
                  AND JSON_TYPE(JSON_EXTRACT(`value`, '$.isNet')) = 'BOOLEAN'
                  AND JSON_EXTRACT(`value`, '$.type') IS NULL;
            SQL
        );

        $this->registerIndexer($connection, 'rule.indexer');
    }
}
