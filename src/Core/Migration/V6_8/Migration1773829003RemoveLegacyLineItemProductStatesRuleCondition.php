<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_8;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('inventory')]
class Migration1773829003RemoveLegacyLineItemProductStatesRuleCondition extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1773829003;
    }

    public function update(Connection $connection): void
    {
        $deleted = $connection->executeStatement(
            'DELETE FROM `rule_condition` WHERE `type` = :legacyType',
            ['legacyType' => 'cartLineItemProductStates']
        );

        if ($deleted > 0) {
            $this->registerIndexer($connection, 'rule.indexer');
        }
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}

