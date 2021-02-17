<?php declare(strict_types=1);

namespace Shopware\Core\Migration\Test;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Migration\Migration1607514878AddOrderDeliveryRetourTransition;

class Migration1607514878AddOrderDeliveryRetourTransitionTest extends TestCase
{
    use KernelTestBehaviour;

    public function testDeliveryRetourActionFromPartiallyReturnedToReturnedIsAdded(): void
    {
        $connection = $this->getContainer()->get(Connection::class);

        $migration = new Migration1607514878AddOrderDeliveryRetourTransition();
        $migration->update($connection);

        $stateMachine = $connection->fetchColumn('SELECT id FROM state_machine WHERE technical_name = :name', ['name' => 'order_delivery.state']);
        $returnedPartially = $connection->fetchColumn('SELECT id FROM state_machine_state WHERE technical_name = :name AND state_machine_id = :id', ['name' => 'returned_partially', 'id' => $stateMachine]);
        $returned = $connection->fetchColumn('SELECT id FROM state_machine_state WHERE technical_name = :name AND state_machine_id = :id', ['name' => 'returned', 'id' => $stateMachine]);

        $existedRetourTransition = $connection->fetchColumn('
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
