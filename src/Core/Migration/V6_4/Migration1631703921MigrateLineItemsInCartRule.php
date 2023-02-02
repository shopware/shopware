<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_4;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal (flag:FEATURE_NEXT_17016) move into new migration if the feature releases
 */
class Migration1631703921MigrateLineItemsInCartRule extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1631703921;
    }

    public function update(Connection $connection): void
    {
        if (!Feature::isActive('FEATURE_NEXT_17016')) {
            return;
        }

        // find all the deprecated rules
        $ruleConditions = $connection->fetchAllAssociative('SELECT DISTINCT rule_id FROM rule_condition WHERE type = "cartLineItemsInCart"');
        $ruleIds = array_map(function ($condition) {
            return $condition['rule_id'];
        }, $ruleConditions);

        // migrate the rule condition types
        $connection->executeStatement('UPDATE rule_condition SET type = "cartLineItem" WHERE type = "cartLineItemsInCart"');

        // clear the payload in the updated rules
        $connection->executeStatement('UPDATE rule SET payload = NULL WHERE id in (:rule_ids)', [
            'rule_ids' => $ruleIds,
        ], [
            'rule_ids' => Connection::PARAM_STR_ARRAY,
        ]);

        // rebuild payload on rule (because it contains the conditions serialized)
        $this->registerIndexer($connection, 'rule.indexer');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
