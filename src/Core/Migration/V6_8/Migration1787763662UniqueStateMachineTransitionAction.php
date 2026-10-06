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
    private const INDEX_NAME = 'uniq.state_machine_transition.action_name_from_state';

    private const PREVIOUS_INDEX_NAME = 'uniq.state_machine_transition.action_name_state_machine';

    public function getCreationTimestamp(): int
    {
        return 1787763662;
    }

    public function update(Connection $connection): void
    {
        if ($this->indexExists($connection, 'state_machine_transition', self::INDEX_NAME)) {
            return;
        }

        $connection->executeStatement(
            'DELETE `duplicate` FROM `state_machine_transition` AS `duplicate`
             INNER JOIN `state_machine_transition` AS `keeper`
                 ON `keeper`.`state_machine_id` = `duplicate`.`state_machine_id`
                 AND `keeper`.`from_state_id` = `duplicate`.`from_state_id`
                 AND `keeper`.`action_name` = `duplicate`.`action_name`
                 AND (`keeper`.`created_at` < `duplicate`.`created_at`
                     OR (`keeper`.`created_at` = `duplicate`.`created_at` AND `keeper`.`id` < `duplicate`.`id`))'
        );

        $this->dropIndexIfExists($connection, 'state_machine_transition', self::PREVIOUS_INDEX_NAME);

        $this->executeDdlStatement(
            $connection,
            \sprintf(
                'ALTER TABLE `state_machine_transition`
                 ADD UNIQUE `%s` (`action_name`, `state_machine_id`, `from_state_id`)',
                self::INDEX_NAME
            )
        );
    }
}
