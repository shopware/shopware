<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_8;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('checkout')]
class Migration1787763662UniqueStateMachineTransitionAction extends MigrationStep
{
    private const UNIQUE_INDEX_NAME = 'uniq.state_machine_transition.action_name_state_machine';

    public function getCreationTimestamp(): int
    {
        return 1787763662;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(
            'DELETE `duplicate` FROM `state_machine_transition` AS `duplicate`
             INNER JOIN `state_machine_transition` AS `keeper`
                 ON `keeper`.`state_machine_id` = `duplicate`.`state_machine_id`
                 AND `keeper`.`from_state_id` = `duplicate`.`from_state_id`
                 AND `keeper`.`action_name` = `duplicate`.`action_name`
                 AND (`keeper`.`created_at` < `duplicate`.`created_at`
                     OR (`keeper`.`created_at` = `duplicate`.`created_at` AND `keeper`.`id` < `duplicate`.`id`))'
        );

        if ($this->uniqueIndexIncludesToState($connection)) {
            $this->dropIndexIfExists($connection, 'state_machine_transition', self::UNIQUE_INDEX_NAME);
        }

        if (!$this->indexExists($connection, 'state_machine_transition', self::UNIQUE_INDEX_NAME)) {
            $this->executeDdlStatement(
                $connection,
                'ALTER TABLE `state_machine_transition`
                 ADD UNIQUE `uniq.state_machine_transition.action_name_state_machine` (`action_name`, `state_machine_id`, `from_state_id`)'
            );
        }
    }

    private function uniqueIndexIncludesToState(Connection $connection): bool
    {
        return (bool) $connection->fetchOne(
            'SELECT 1 FROM `information_schema`.`STATISTICS`
             WHERE `TABLE_SCHEMA` = DATABASE()
                 AND `TABLE_NAME` = :table
                 AND `INDEX_NAME` = :index
                 AND `COLUMN_NAME` = :column',
            [
                'table' => 'state_machine_transition',
                'index' => self::UNIQUE_INDEX_NAME,
                'column' => 'to_state_id',
            ]
        );
    }
}
