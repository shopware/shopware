<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('after-sales')]
class Migration1754398573ChangeAllLineItemsRuleValueType extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1754398573;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(
            <<<'SQL'
                UPDATE `rule_condition`
                SET `value` = JSON_OBJECT('types', JSON_ARRAY(JSON_UNQUOTE(JSON_EXTRACT(`value`, '$.type'))))
                WHERE `type` = 'allLineItemsContainer'
                  AND JSON_VALID(`value`)
                  AND JSON_EXTRACT(`value`, '$.type') IS NOT NULL
                  AND JSON_TYPE(JSON_EXTRACT(`value`, '$.type')) = 'STRING';
            SQL
        );

        // condition payload has to be serialized again
        $this->registerIndexer($connection, 'rule.indexer');
    }
}
