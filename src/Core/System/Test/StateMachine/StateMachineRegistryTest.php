<?php declare(strict_types=1);

namespace Shopware\Core\System\Test\StateMachine;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\StateMachine\Exception\StateMachineNotFoundException;
use Shopware\Core\System\StateMachine\Exception\StateMachineWithoutInitialStateException;
use Shopware\Core\System\StateMachine\StateMachineRegistry;

class StateMachineRegistryTest extends TestCase
{
    use KernelTestBehaviour;

    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var string
     */
    private $stateMachineId;

    /**
     * @var string
     */
    private $openId;

    /**
     * @var string
     */
    private $inProgressId;

    /**
     * @var string
     */
    private $closedId;

    /**
     * @var string
     */
    private $stateMachineName;

    /**
     * @var StateMachineRegistry
     */
    private $stateMachineRegistry;

    /**
     * @var EntityRepositoryInterface
     */
    private $stateMachineRepository;

    /**
     * @var string
     */
    private $stateMachineWithoutInitialId;

    /**
     * @var string
     */
    private $stateMachineWithoutInitialName;

    protected function setUp(): void
    {
        $this->connection = $this->getContainer()->get(Connection::class);
        $this->stateMachineRegistry = $this->getContainer()->get(StateMachineRegistry::class);
        $this->stateMachineRepository = $this->getContainer()->get('state_machine.repository');

        $this->stateMachineName = 'test_state_machine';
        $this->stateMachineId = Uuid::randomHex();
        $this->openId = Uuid::randomHex();
        $this->inProgressId = Uuid::randomHex();
        $this->closedId = Uuid::randomHex();

        $this->stateMachineWithoutInitialId = Uuid::randomHex();
        $this->stateMachineWithoutInitialName = 'test_broken_state_machine';

        $nullableTable = <<<EOF
DROP TABLE IF EXISTS _test_nullable;
CREATE TABLE `_test_nullable` (
  `id` varbinary(16) NOT NULL,
  `state` varchar(255) NULL,
  PRIMARY KEY `id` (`id`)
);
EOF;
        $this->connection->executeUpdate($nullableTable);
        $this->connection->beginTransaction();
    }

    public function tearDown(): void
    {
        $this->connection->rollBack();
        $this->connection->executeUpdate('DROP TABLE `_test_nullable`');
    }

    public function testNonExistingStateMachine(): void
    {
        $this->expectException(StateMachineNotFoundException::class);

        $context = Context::createDefaultContext();

        $this->stateMachineRegistry->getStateMachine('wusel', $context);
    }

    public function testStateMachineMustHaveInitialState(): void
    {
        $context = Context::createDefaultContext();
        $this->createStateMachineWithoutInitialState($context);

        $stateMachine = $this->stateMachineRegistry->getStateMachine($this->stateMachineWithoutInitialName, $context);
        static::assertNotNull($stateMachine);

        $this->expectException(StateMachineWithoutInitialStateException::class);
        $this->stateMachineRegistry->getInitialState($this->stateMachineWithoutInitialName, $context);
    }

    public function testStateMachineShouldIncludeRelations(): void
    {
        $context = Context::createDefaultContext();
        $this->createStateMachine($context);

        $stateMachine = $this->stateMachineRegistry->getStateMachine($this->stateMachineName, $context);

        static::assertNotNull($stateMachine);
        static::assertNotNull($stateMachine->getStates());
        static::assertEquals(3, $stateMachine->getStates()->count());
        static::assertNotNull($stateMachine->getTransitions());
        static::assertEquals(4, $stateMachine->getTransitions()->count());
    }

    private function createStateMachine(Context $context): void
    {
        $this->stateMachineRepository->upsert([
            [
                'id' => $this->stateMachineId,
                'technicalName' => $this->stateMachineName,
                'translations' => [
                    'en-GB' => ['name' => 'Order state'],
                    'de-DE' => ['name' => 'Bestellungsstatus'],
                ],
                'states' => [
                    ['id' => $this->openId, 'technicalName' => 'open', 'name' => 'Open'],
                    ['id' => $this->inProgressId, 'technicalName' => 'in_progress', 'name' => 'In progress'],
                    ['id' => $this->closedId, 'technicalName' => 'closed', 'name' => 'Closed'],
                ],
                'transitions' => [
                    ['actionName' => 'start', 'fromStateId' => $this->openId, 'toStateId' => $this->inProgressId],

                    ['actionName' => 'reopen', 'fromStateId' => $this->inProgressId, 'toStateId' => $this->openId],
                    ['actionName' => 'close', 'fromStateId' => $this->inProgressId, 'toStateId' => $this->closedId],

                    ['actionName' => 'reopen', 'fromStateId' => $this->closedId, 'toStateId' => $this->openId],
                ],
            ],
        ], $context);
    }

    private function createStateMachineWithoutInitialState(Context $context): void
    {
        $this->stateMachineRepository->upsert([
            [
                'id' => $this->stateMachineWithoutInitialId,
                'technicalName' => $this->stateMachineWithoutInitialName,
                'translations' => [
                    'en-GB' => ['name' => 'Order state'],
                    'de-DE' => ['name' => 'Bestellungsstatus'],
                ],
            ],
        ], $context);
    }
}
