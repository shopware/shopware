<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Mcp\Tool;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\Context\McpContextProvider;
use Shopware\Core\Framework\Mcp\Tool\OrderStateTool;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateCollection;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateEntity;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineTransition\StateMachineTransitionEntity;
use Shopware\Core\System\StateMachine\StateMachineRegistry;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(OrderStateTool::class)]
class OrderStateToolTest extends TestCase
{
    public function testDryRunWithCancelAction(): void
    {
        $order = $this->buildOrder('open', 'open', 'open');
        $tool = $this->createTool($order, availableActions: ['cancel']);

        $output = ($tool)(orderNumber: '10001', orderAction: 'cancel', transactionAction: 'cancel', deliveryAction: 'cancel', dryRun: true);
        $data = json_decode($output, true, 512, \JSON_THROW_ON_ERROR);

        static::assertTrue($data['success']);
        static::assertTrue($data['_meta']['dryRun']);
        static::assertSame('cancel', $data['data']['order']['action']);
        static::assertTrue($data['data']['order']['actionValid']);
        static::assertCount(1, $data['data']['transactions']);
        static::assertCount(1, $data['data']['deliveries']);
    }

    public function testCommitWithProcessAndShip(): void
    {
        $order = $this->buildOrder('open', 'open', 'open');
        $tool = $this->createTool($order, availableActions: ['process', 'ship'], executeTransitions: true);

        $output = ($tool)(orderNumber: '10001', orderAction: 'process', deliveryAction: 'ship', dryRun: false);
        $data = json_decode($output, true, 512, \JSON_THROW_ON_ERROR);

        static::assertTrue($data['success']);
        static::assertFalse($data['_meta']['dryRun']);
        static::assertTrue($data['data']['order']['executed']);
        static::assertArrayNotHasKey('transactions', $data['data']);
        static::assertTrue($data['data']['deliveries'][0]['executed']);
    }

    public function testOnlyTransactionAction(): void
    {
        $order = $this->buildOrder('open', 'open', 'open');
        $tool = $this->createTool($order, availableActions: ['paid'], executeTransitions: true);

        $output = ($tool)(orderNumber: '10001', transactionAction: 'paid', dryRun: false);
        $data = json_decode($output, true, 512, \JSON_THROW_ON_ERROR);

        static::assertTrue($data['success']);
        static::assertArrayNotHasKey('order', $data['data']);
        static::assertTrue($data['data']['transactions'][0]['executed']);
        static::assertArrayNotHasKey('deliveries', $data['data']);
    }

    public function testTransitionNotAvailableReturnsNote(): void
    {
        $order = $this->buildOrder('in_progress', 'open', 'open');
        $tool = $this->createTool($order, availableActions: []);

        $output = ($tool)(orderNumber: '10001', orderAction: 'cancel', dryRun: false);
        $data = json_decode($output, true, 512, \JSON_THROW_ON_ERROR);

        static::assertTrue($data['success']);
        static::assertFalse($data['data']['order']['executed']);
        static::assertStringContainsString('not available', $data['data']['order']['note']);
    }

    public function testDryRunShowsAvailableTransitions(): void
    {
        $order = $this->buildOrder('open', 'open', 'open');
        $tool = $this->createTool($order, availableActions: ['cancel', 'process']);

        $output = ($tool)(orderNumber: '10001', orderAction: 'cancel', dryRun: true);
        $data = json_decode($output, true, 512, \JSON_THROW_ON_ERROR);

        static::assertTrue($data['success']);
        static::assertCount(2, $data['data']['order']['availableTransitions']);
    }

    public function testDeniesWritePermissionOnCommit(): void
    {
        $source = new AdminApiSource(null, null);
        $source->setPermissions(['order:read']);
        $context = new Context($source, [], Defaults::CURRENCY, [Defaults::LANGUAGE_SYSTEM]);

        $order = $this->buildOrder('open', 'open', 'open');
        $tool = $this->createToolWithContext($order, $context, availableActions: ['cancel']);

        $output = ($tool)(orderNumber: '10001', orderAction: 'cancel', dryRun: false);
        $data = json_decode($output, true, 512, \JSON_THROW_ON_ERROR);

        static::assertFalse($data['success']);
        static::assertStringContainsString('Missing privilege', $data['error']);
    }

    public function testDeniesAccessWithoutReadPermission(): void
    {
        $source = new AdminApiSource(null, null);
        $source->setPermissions([]);
        $context = new Context($source, [], Defaults::CURRENCY, [Defaults::LANGUAGE_SYSTEM]);

        $registry = $this->createMock(DefinitionInstanceRegistry::class);
        $registry->expects($this->never())->method('getRepository');

        $contextProvider = static::createStub(McpContextProvider::class);
        $contextProvider->method('getContext')->willReturn($context);

        $tool = new OrderStateTool(
            $registry,
            $contextProvider,
            static::createStub(StateMachineRegistry::class),
            static::createStub(Connection::class),
        );
        $output = ($tool)(orderNumber: '10001', orderAction: 'cancel');

        $data = json_decode($output, true, 512, \JSON_THROW_ON_ERROR);

        static::assertFalse($data['success']);
        static::assertStringContainsString('order:read', $data['error']);
    }

    public function testOnlyTransactionWritePrivilegeCheckedWhenOnlyTransactionAction(): void
    {
        $source = new AdminApiSource(null, null);
        $source->setPermissions(['order:read', 'order_transaction:update']);
        $context = new Context($source, [], Defaults::CURRENCY, [Defaults::LANGUAGE_SYSTEM]);

        $order = $this->buildOrder('open', 'open', 'open');
        $tool = $this->createToolWithContext($order, $context, availableActions: ['paid'], executeTransitions: true);

        $output = ($tool)(orderNumber: '10001', transactionAction: 'paid', dryRun: false);
        $data = json_decode($output, true, 512, \JSON_THROW_ON_ERROR);

        static::assertTrue($data['success']);
        static::assertTrue($data['data']['transactions'][0]['executed']);
    }

    public function testOrderNotFoundReturnsError(): void
    {
        $tool = $this->createTool(null, availableActions: []);

        $output = ($tool)(orderNumber: '99999', orderAction: 'cancel');
        $data = json_decode($output, true, 512, \JSON_THROW_ON_ERROR);

        static::assertFalse($data['success']);
        static::assertSame('Order not found.', $data['error']);
    }

    public function testLookupByOrderId(): void
    {
        $order = $this->buildOrder('open', 'open', 'open');
        $tool = $this->createTool($order, availableActions: ['cancel']);

        $output = ($tool)(orderId: $order->getId(), orderAction: 'cancel', dryRun: true);
        $data = json_decode($output, true, 512, \JSON_THROW_ON_ERROR);

        static::assertTrue($data['success']);
        static::assertSame('cancel', $data['data']['order']['action']);
    }

    public function testNoIdentifierReturnsError(): void
    {
        $tool = $this->createTool(null, availableActions: []);

        $output = ($tool)(orderAction: 'cancel');
        $data = json_decode($output, true, 512, \JSON_THROW_ON_ERROR);

        static::assertFalse($data['success']);
        static::assertStringContainsString('orderNumber or orderId', $data['error']);
    }

    public function testNoActionReturnsError(): void
    {
        $tool = $this->createTool(null, availableActions: []);

        $output = ($tool)(orderNumber: '10001');
        $data = json_decode($output, true, 512, \JSON_THROW_ON_ERROR);

        static::assertFalse($data['success']);
        static::assertStringContainsString('orderAction, transactionAction, or deliveryAction', $data['error']);
    }

    public function testGetAvailableTransitionsExceptionTreatsAsEmpty(): void
    {
        $order = $this->buildOrder('open', 'open', 'open');
        $tool = $this->createTool($order, availableActions: [], throwOnGetTransitions: true);

        $output = ($tool)(orderNumber: '10001', orderAction: 'cancel', dryRun: true);
        $data = json_decode($output, true, 512, \JSON_THROW_ON_ERROR);

        static::assertTrue($data['success']);
        static::assertFalse($data['data']['order']['actionValid']);
        static::assertSame([], $data['data']['order']['availableTransitions']);
    }

    public function testNonDryRunWrapsTransitionsInTransaction(): void
    {
        $order = $this->buildOrder('open', 'open', 'open');

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('transactional')
            ->willReturnCallback(fn (callable $fn) => $fn());

        $tool = $this->createToolWithContext($order, Context::createDefaultContext(), ['process'], true, false, $connection);

        $output = ($tool)(orderNumber: '10001', orderAction: 'process', dryRun: false);
        $data = json_decode($output, true, 512, \JSON_THROW_ON_ERROR);

        static::assertTrue($data['success']);
        static::assertFalse($data['_meta']['dryRun']);
    }

    public function testDryRunDoesNotUseTransaction(): void
    {
        $order = $this->buildOrder('open', 'open', 'open');

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->never())->method('transactional');

        $tool = $this->createToolWithContext($order, Context::createDefaultContext(), ['cancel'], false, false, $connection);

        ($tool)(orderNumber: '10001', orderAction: 'cancel', dryRun: true);
    }

    /**
     * @param list<string> $availableActions
     */
    private function createTool(
        ?OrderEntity $order,
        array $availableActions,
        bool $executeTransitions = false,
        bool $throwOnGetTransitions = false,
    ): OrderStateTool {
        return $this->createToolWithContext($order, Context::createDefaultContext(), $availableActions, $executeTransitions, $throwOnGetTransitions);
    }

    /**
     * @param list<string> $availableActions
     */
    private function createToolWithContext(
        ?OrderEntity $order,
        Context $context,
        array $availableActions,
        bool $executeTransitions = false,
        bool $throwOnGetTransitions = false,
        ?Connection $connection = null,
    ): OrderStateTool {
        $collection = new OrderCollection();
        if ($order !== null) {
            $collection->add($order);
        }

        $result = new EntitySearchResult('order', $collection->count(), $collection, null, new Criteria(), $context);

        $repository = static::createStub(EntityRepository::class);
        $repository->method('search')->willReturn($result);

        $registry = static::createStub(DefinitionInstanceRegistry::class);
        $registry->method('getRepository')->willReturn($repository);

        $contextProvider = static::createStub(McpContextProvider::class);
        $contextProvider->method('getContext')->willReturn($context);

        $stateMachineRegistry = static::createStub(StateMachineRegistry::class);

        if ($throwOnGetTransitions) {
            $stateMachineRegistry->method('getAvailableTransitions')
                ->willThrowException(new \RuntimeException('State machine not found'));
        } else {
            $transitions = [];
            foreach ($availableActions as $action) {
                $transition = new StateMachineTransitionEntity();
                $transition->setId(Uuid::randomHex());
                $transition->setActionName($action);
                $transition->setUniqueIdentifier(Uuid::randomHex());
                $toState = new StateMachineStateEntity();
                $toState->setTechnicalName($action . '_target');
                $transition->setToStateMachineState($toState);
                $transitions[] = $transition;
            }

            $stateMachineRegistry->method('getAvailableTransitions')->willReturn($transitions);
        }

        if ($executeTransitions) {
            $stateCollection = new StateMachineStateCollection();
            $stateMachineRegistry->method('transition')->willReturn($stateCollection);
        }

        if ($connection === null) {
            $connection = $this->createMock(Connection::class);
            $connection->method('transactional')->willReturnCallback(fn (callable $fn) => $fn());
        }

        return new OrderStateTool(
            $registry,
            $contextProvider,
            $stateMachineRegistry,
            $connection,
        );
    }

    private function buildOrder(string $orderState, string $transactionState, string $deliveryState): OrderEntity
    {
        $orderId = Uuid::randomHex();

        $state = new StateMachineStateEntity();
        $state->setId(Uuid::randomHex());
        $state->setTechnicalName($orderState);
        $state->setUniqueIdentifier(Uuid::randomHex());

        $txState = new StateMachineStateEntity();
        $txState->setId(Uuid::randomHex());
        $txState->setTechnicalName($transactionState);
        $txState->setUniqueIdentifier(Uuid::randomHex());

        $transaction = new OrderTransactionEntity();
        $transaction->setId(Uuid::randomHex());
        $transaction->setStateMachineState($txState);
        $transaction->setAmount(new CalculatedPrice(99.99, 99.99, new CalculatedTaxCollection(), new TaxRuleCollection()));
        $transaction->setUniqueIdentifier(Uuid::randomHex());

        $delState = new StateMachineStateEntity();
        $delState->setId(Uuid::randomHex());
        $delState->setTechnicalName($deliveryState);
        $delState->setUniqueIdentifier(Uuid::randomHex());

        $delivery = new OrderDeliveryEntity();
        $delivery->setId(Uuid::randomHex());
        $delivery->setStateMachineState($delState);
        $delivery->setUniqueIdentifier(Uuid::randomHex());

        $order = new OrderEntity();
        $order->setId($orderId);
        $order->setOrderNumber('10001');
        $order->setStateMachineState($state);
        $order->setTransactions(new OrderTransactionCollection([$transaction]));
        $order->setDeliveries(new OrderDeliveryCollection([$delivery]));
        $order->setUniqueIdentifier($orderId);

        return $order;
    }
}
