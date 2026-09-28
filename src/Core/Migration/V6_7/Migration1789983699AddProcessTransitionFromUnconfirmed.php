<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Migration\Traits\StateMachineMigration;
use Shopware\Core\Migration\Traits\StateMachineMigrationTrait;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineTransition\StateMachineTransitionActions;

/**
 * @internal
 */
#[Package('checkout')]
class Migration1789983699AddProcessTransitionFromUnconfirmed extends MigrationStep
{
    use StateMachineMigrationTrait;

    public function getCreationTimestamp(): int
    {
        return 1789983699;
    }

    public function update(Connection $connection): void
    {
        $this->import(
            new StateMachineMigration(
                OrderTransactionStates::STATE_MACHINE,
                'Zahlungsstatus',
                'Payment state',
                [],
                [
                    StateMachineMigration::transition(
                        StateMachineTransitionActions::ACTION_PROCESS,
                        OrderTransactionStates::STATE_UNCONFIRMED,
                        OrderTransactionStates::STATE_IN_PROGRESS,
                    ),
                ],
            ),
            $connection
        );
    }
}
