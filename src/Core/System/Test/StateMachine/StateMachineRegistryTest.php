<?php declare(strict_types=1);

namespace Shopware\Core\System\Test\StateMachine;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Order\OrderStates;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\TestCaseBase\BasicTestDataBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\StateMachine\Exception\StateMachineNotFoundException;
use Shopware\Core\System\StateMachine\Exception\StateMachineWithoutInitialStateException;
use Shopware\Core\System\StateMachine\StateMachineRegistry;
use Shopware\Core\System\StateMachine\StateMachineTransitionWalker;

class StateMachineRegistryTest extends TestCase
{
    use KernelTestBehaviour;
    use BasicTestDataBehaviour;

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
     * @var EntityRepositoryInterface
     */
    private $orderRepository;

    /**
     * @var StateMachineTransitionWalker
     */
    private $stateMachineTransitionWalker;

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
        $this->stateMachineRegistry = new StateMachineRegistry(
            $this->getContainer()->get('state_machine.repository'),
            $this->getContainer()->get('state_machine_state.repository'),
            $this->getContainer()->get('state_machine_history.repository'),
            $this->getContainer()->get('event_dispatcher'),
            $this->getContainer()->get(DefinitionInstanceRegistry::class)
        );
        $this->stateMachineRepository = $this->getContainer()->get('state_machine.repository');
        $this->orderRepository = $this->getContainer()->get('order.repository');
        $this->stateMachineTransitionWalker = new StateMachineTransitionWalker(
            $this->stateMachineRegistry,
            $this->getContainer()->get(DefinitionInstanceRegistry::class),
            $this->getContainer()->get('state_machine_state.repository')
        );

        $this->stateMachineName = 'test_state_machine';
        $this->stateMachineId = Uuid::randomHex();
        $this->openId = Uuid::randomHex();
        $this->inProgressId = Uuid::randomHex();
        $this->closedId = Uuid::randomHex();

        $this->stateMachineWithoutInitialId = Uuid::randomHex();
        $this->stateMachineWithoutInitialName = 'test_broken_state_machine';
        $this->connection->beginTransaction();
    }

    public function tearDown(): void
    {
        $this->connection->rollBack();
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

    public function testWalkerWalksPath(): void
    {
        $context = Context::createDefaultContext();
        $this->createStateMachine($context);
        $testId = Uuid::randomHex();
        $this->createOrder($testId, $context);
        /** @var OrderEntity $order */
        $order = $this->orderRepository->search(new Criteria([$testId]), $context)->first();
        $nowStateId = $order->getStateId();
        static::assertEquals($this->getStateId(OrderStates::STATE_OPEN, OrderStates::STATE_MACHINE), $nowStateId);

        $this->stateMachineTransitionWalker->walkPath(
            'order',
            $testId,
            'stateId',
            OrderStates::STATE_COMPLETED,
            $context
        );

        /** @var OrderEntity $order */
        $order = $this->orderRepository->search(new Criteria([$testId]), $context)->first();
        $nowStateId = $order->getStateId();
        static::assertEquals($this->getStateId(OrderStates::STATE_COMPLETED, OrderStates::STATE_MACHINE), $nowStateId);
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

    private function createOrder(string $id, Context $context): void
    {
        $addressId = Uuid::randomHex();

        $data = [
            'id' => $id,
            'billingAddressId' => $addressId,
            'currencyId' => Defaults::CURRENCY,
            'languageId' => Defaults::LANGUAGE_SYSTEM,
            'salesChannelId' => Defaults::SALES_CHANNEL,
            'orderDateTime' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            'currencyFactor' => 1,
            'stateId' => $this->getStateId(OrderStates::STATE_OPEN, OrderStates::STATE_MACHINE),
            'price' => new CartPrice(0, 0, 0, new CalculatedTaxCollection(), new TaxRuleCollection(), CartPrice::TAX_STATE_GROSS),
            'shippingCosts' => new CalculatedPrice(0, 0, new CalculatedTaxCollection(), new TaxRuleCollection()),
            'orderCustomer' => [
                'salutationId' => $this->getSalutationId(),
                'email' => 'test@example.test',
                'firstName' => 'John',
                'lastName' => 'Doe',
            ],
            'addresses' => [
                [
                    'id' => $addressId,
                    'countryId' => $this->getValidCountryId(),
                    'salutationId' => $this->getValidSalutationId(),
                    'firstName' => 'asd',
                    'lastName' => 'asd',
                    'street' => 'asd',
                    'zipcode' => 'asd',
                    'city' => 'asd',
                ],
            ],
        ];

        $this->orderRepository->create([$data], $context);
    }

    private function getStateId(string $state, string $machine)
    {
        return $this->connection
            ->fetchColumn('
                SELECT LOWER(HEX(state_machine_state.id))
                FROM state_machine_state
                    INNER JOIN  state_machine
                    ON state_machine.id = state_machine_state.state_machine_id
                    AND state_machine.technical_name = :machine
                WHERE state_machine_state.technical_name = :state
            ', [
                'state' => $state,
                'machine' => $machine,
            ]);
    }

    private function getSalutationId()
    {
        return $this->connection->fetchColumn('SELECT LOWER(HEX(id)) FROM salutation');
    }
}
