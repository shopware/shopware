<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_3;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Migration\V6_3\Migration1607514878AddOrderDeliveryRetourTransition;

/**
 * @internal
 *
 * @covers \Shopware\Core\Migration\V6_3\Migration1607514878AddOrderDeliveryRetourTransition
 */
class Migration1607514878AddOrderDeliveryRetourTransitionTest extends TestCase
{
    public function testDeliveryRetourActionFromPartiallyReturnedToReturnedIsAdded(): void
    {
        $connection = KernelLifecycleManager::getConnection();

        $migration = new Migration1607514878AddOrderDeliveryRetourTransition();
        $migration->update($connection);

        $stateMachine = $connection->fetchOne('SELECT id FROM state_machine WHERE technical_name = :name', ['name' => 'order_delivery.state']);
        $returnedPartially = $connection->fetchOne('SELECT id FROM state_machine_state WHERE technical_name = :name AND state_machine_id = :id', ['name' => 'returned_partially', 'id' => $stateMachine]);
        $returned = $connection->fetchOne('SELECT id FROM state_machine_state WHERE technical_name = :name AND state_machine_id = :id', ['name' => 'returned', 'id' => $stateMachine]);

        $existedRetourTransition = $connection->fetchOne('
            SELECT `id` FROM `state_machine_transition`
            WHERE `action_name` = :actionName
            AND `state_machine_id` = :stateMachineId
            AND `from_state_id` = :fromStateId
            AND `to_state_id` = :toStateId;
        ', [
            'actionName' => 'retour',
            'stateMachineId' => $stateMachine,
            'fromStateId' => $returnedPartially,
            'toStateId' => $returned,
        ]);

        static::assertNotFalse($existedRetourTransition);
        static::assertNotNull($existedRetourTransition);
    }
}
