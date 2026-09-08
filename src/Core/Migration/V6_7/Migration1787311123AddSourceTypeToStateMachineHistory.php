<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineHistory\StateMachineHistoryDefinition;

/**
 * @internal
 */
#[Package('checkout')]
class Migration1787311123AddSourceTypeToStateMachineHistory extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1787311123;
    }

    public function update(Connection $connection): void
    {
        $this->addColumn($connection, StateMachineHistoryDefinition::ENTITY_NAME, 'source_type', 'VARCHAR(32)');
    }
}
