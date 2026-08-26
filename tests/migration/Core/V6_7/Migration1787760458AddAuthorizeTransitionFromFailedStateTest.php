<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Migration\V6_7\Migration1787760458AddAuthorizeTransitionFromFailedState;
use Shopware\Tests\Migration\MigrationTestTrait;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(Migration1787760458AddAuthorizeTransitionFromFailedState::class)]
class Migration1787760458AddAuthorizeTransitionFromFailedStateTest extends TestCase
{
    use MigrationTestTrait;

    private Connection $connection;

    private Migration1787760458AddAuthorizeTransitionFromFailedState $migration;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection = KernelLifecycleManager::getConnection();
        $this->migration = new Migration1787760458AddAuthorizeTransitionFromFailedState();
    }

    public function testAPaymentCanBeAuthorizedAfterItWasFailed(): void
    {
        $this->connection->executeStatement(
            'DELETE `state_machine_transition` FROM `state_machine_transition`
             INNER JOIN `state_machine` ON `state_machine`.`id` = `state_machine_transition`.`state_machine_id`
             INNER JOIN `state_machine_state` ON `state_machine_state`.`id` = `state_machine_transition`.`from_state_id`
             WHERE `state_machine`.`technical_name` = :stateMachine
               AND `state_machine_state`.`technical_name` = :fromState
               AND `state_machine_transition`.`action_name` = :actionName',
            ['stateMachine' => 'order_transaction.state', 'fromState' => 'failed', 'actionName' => 'authorize']
        );

        // run twice to prove idempotency
        $this->migration->update($this->connection);
        $this->migration->update($this->connection);

        static::assertSame(['authorized'], $this->fetchDestinationsOfAuthorizeFromFailed());
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1787760458, $this->migration->getCreationTimestamp());
    }

    /**
     * @return list<string>
     */
    private function fetchDestinationsOfAuthorizeFromFailed(): array
    {
        /** @var list<string> $destinations */
        $destinations = $this->connection->fetchFirstColumn(
            'SELECT `to_state`.`technical_name`
             FROM `state_machine_transition`
             INNER JOIN `state_machine` ON `state_machine`.`id` = `state_machine_transition`.`state_machine_id`
             INNER JOIN `state_machine_state` AS `from_state` ON `from_state`.`id` = `state_machine_transition`.`from_state_id`
             INNER JOIN `state_machine_state` AS `to_state` ON `to_state`.`id` = `state_machine_transition`.`to_state_id`
             WHERE `state_machine`.`technical_name` = :stateMachine
               AND `from_state`.`technical_name` = :fromState
               AND `state_machine_transition`.`action_name` = :actionName',
            ['stateMachine' => 'order_transaction.state', 'fromState' => 'failed', 'actionName' => 'authorize']
        );

        return $destinations;
    }
}
