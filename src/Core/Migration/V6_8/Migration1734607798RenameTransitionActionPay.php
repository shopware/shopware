<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_8;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('checkout')]
class Migration1734607798RenameTransitionActionPay extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1734607798;
    }

    public function update(Connection $connection): void
    {
        $this->renameTransitionActionPay($connection);
        $this->renameTransitionActionPayPartially($connection);
    }

    protected function renameTransitionActionPay(Connection $connection): void
    {
        $sql = <<<SQL
UPDATE
    state_machine_transition
SET
    action_name = 'paid'
WHERE
    action_name = 'pay'
SQL;

        $connection->executeStatement($sql);
    }

    protected function renameTransitionActionPayPartially(Connection $connection): void
    {
        $sql = <<<SQL
UPDATE
    state_machine_transition
SET
    action_name = 'paid_partially'
WHERE
    action_name = 'pay_partially'
SQL;

        $connection->executeStatement($sql);
    }
}
