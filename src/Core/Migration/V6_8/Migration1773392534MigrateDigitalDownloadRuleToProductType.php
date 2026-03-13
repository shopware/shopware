<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_8;

use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('after-sales')]
class Migration1773392534MigrateDigitalDownloadRuleToProductType extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1773392534;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(
            <<<'SQL'
            UPDATE `rule_condition`
            SET
                `type` = :newType,
                `value` = :newValue
            WHERE `type` = :oldType
              AND JSON_UNQUOTE(JSON_EXTRACT(`value`, '$.operator')) = '='
              AND JSON_UNQUOTE(JSON_EXTRACT(`value`, '$.productState')) = :legacyState
            SQL,
            [
                'newType' => 'cartLineItemProductType',
                'newValue' => \json_encode(
                    ['operator' => '=', 'productType' => ProductDefinition::TYPE_DIGITAL],
                    \JSON_THROW_ON_ERROR
                ),
                'oldType' => 'cartLineItemProductStates',
                'legacyState' => 'is-download',
            ]
        );

        $this->registerIndexer($connection, 'rule.indexer');
    }
}
