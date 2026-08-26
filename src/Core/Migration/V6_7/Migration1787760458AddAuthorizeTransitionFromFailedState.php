<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * A payment transaction can be failed while the payment provider is authorizing it, and the provider then reports
 * the authorization afterwards. Every other confirmation can already be applied on top of a failed transaction -
 * `paid`, `paid_partially`, `process`, `process_unconfirmed` and `reopen` all start there - only `authorize` could
 * not, which left such a transaction failed for good.
 *
 * @internal
 */
#[Package('checkout')]
class Migration1787760458AddAuthorizeTransitionFromFailedState extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1787760458;
    }

    public function update(Connection $connection): void
    {
        $stateMachineId = $connection->fetchOne(
            'SELECT `id` FROM `state_machine` WHERE `technical_name` = :technicalName',
            ['technicalName' => 'order_transaction.state']
        );

        if (!\is_string($stateMachineId)) {
            return;
        }

        $failedId = $this->getStateId($connection, $stateMachineId, 'failed');
        $authorizedId = $this->getStateId($connection, $stateMachineId, 'authorized');

        if ($failedId === null || $authorizedId === null) {
            return;
        }

        $exists = $connection->fetchOne(
            'SELECT `id` FROM `state_machine_transition`
             WHERE `state_machine_id` = :stateMachineId AND `from_state_id` = :fromStateId AND `action_name` = :actionName',
            ['stateMachineId' => $stateMachineId, 'fromStateId' => $failedId, 'actionName' => 'authorize']
        );

        if ($exists !== false) {
            return;
        }

        $connection->insert('state_machine_transition', [
            'id' => Uuid::randomBytes(),
            'action_name' => 'authorize',
            'state_machine_id' => $stateMachineId,
            'from_state_id' => $failedId,
            'to_state_id' => $authorizedId,
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);
    }

    private function getStateId(Connection $connection, string $stateMachineId, string $technicalName): ?string
    {
        $id = $connection->fetchOne(
            'SELECT `id` FROM `state_machine_state` WHERE `state_machine_id` = :stateMachineId AND `technical_name` = :technicalName',
            ['stateMachineId' => $stateMachineId, 'technicalName' => $technicalName]
        );

        return \is_string($id) ? $id : null;
    }
}
