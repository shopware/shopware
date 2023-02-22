<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_6;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1673249981MigrateIsNewCustomerRule extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1673249981;
    }

    public function update(Connection $connection): void
    {
        // find all the deprecated rules
        $ruleConditions = $connection->fetchAllAssociative('SELECT DISTINCT rule_id FROM rule_condition WHERE type = "customerIsNewCustomer"');
        $ruleIds = array_map(fn ($condition) => $condition['rule_id'], $ruleConditions);

        if (empty($ruleIds)) {
            return;
        }

        // migrate the rule condition types
        $connection->executeStatement(
            'UPDATE rule_condition
             SET `type` = "customerDaysSinceFirstLogin",
                 `value` = :value
             WHERE `type` = "customerIsNewCustomer"',
            [
                'value' => json_encode([
                    'operator' => '=',
                    'daysPassed' => 0,
                ]),
            ]
        );

        // clear the payload in the updated rules
        $connection->executeStatement('UPDATE rule SET payload = NULL WHERE id in (:rule_ids)', [
            'rule_ids' => $ruleIds,
        ], [
            'rule_ids' => ArrayParameterType::STRING,
        ]);

        // rebuild payload on rule (because it contains the conditions serialized)
        $this->registerIndexer($connection, 'rule.indexer');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
