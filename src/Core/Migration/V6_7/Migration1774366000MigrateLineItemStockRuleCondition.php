<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('after-sales')]
class Migration1774366000MigrateLineItemStockRuleCondition extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1774366000;
    }

    public function update(Connection $connection): void
    {
        $ruleIds = $connection->fetchFirstColumn(
            'SELECT DISTINCT `rule_id` FROM `rule_condition` WHERE `type` = :oldType',
            ['oldType' => 'cartLineItemStock']
        );

        if ($ruleIds === []) {
            return;
        }

        $connection->executeStatement(
            'UPDATE `rule_condition`
             SET `type` = :newType
             WHERE `type` = :oldType',
            [
                'newType' => 'cartLineItemActualStock',
                'oldType' => 'cartLineItemStock',
            ]
        );

        $connection->executeStatement(
            'UPDATE `rule` SET `payload` = NULL WHERE `id` IN (:ruleIds)',
            ['ruleIds' => $ruleIds],
            ['ruleIds' => ArrayParameterType::BINARY]
        );

        $this->registerIndexer($connection, 'rule.indexer');
    }
}
