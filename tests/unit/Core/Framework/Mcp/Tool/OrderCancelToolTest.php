<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Mcp\Tool;

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
use Shopware\Core\Framework\Mcp\Tool\OrderCancelTool;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateCollection;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateEntity;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineTransition\StateMachineTransitionEntity;
use Shopware\Core\System\StateMachine\StateMachineRegistry;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(OrderCancelTool::class)]
class OrderCancelToolTest extends TestCase
{
    public function testDryRunReturnsPreviewWithCorrectTransitions(): void
    {
        $order = $this->buildOrder('open', 'open', 'open');
        $tool = $this->createTool($order, availableActions: ['cancel']);

        $output = ($tool)(orderNumber: '10001', dryRun: true);
        $data = json_decode($output, true, 512, \JSON_THROW_ON_ERROR);

        static::assertTrue($data['success']);
        static::assertTrue($data['_meta']['dryRun']);

        static::assertSame('cancel', $data['data']['order']['action']);
        static::assertFalse($data['data']['order']['executed']);
        static::assertSame('Will execute on commit', $data['data']['order']['note']);

        static::assertCount(1, $data['data']['transactions']);
        static::assertSame('cancel', $data['data']['transactions'][0]['action']);
        static::assertFalse($data['data']['transactions'][0]['executed']);

        static::assertCount(1, $data['data']['deliveries']);
        static::assertSame('cancel', $data['data']['deliveries'][0]['action']);
        static::assertFalse($data['data']['deliveries'][0]['executed']);
    }

    public function testCommitExecutesAllTransitions(): void
    {
        $order = $this->buildOrder('open', 'open', 'open');
        $tool = $this->createTool($order, availableActions: ['cancel'], executeTransitions: true);

        $output = ($tool)(orderNumber: '10001', dryRun: false);
        $data = json_decode($output, true, 512, \JSON_THROW_ON_ERROR);

        static::assertTrue($data['success']);
        static::assertFalse($data['_meta']['dryRun']);
        static::assertTrue($data['data']['order']['executed']);
        static::assertTrue($data['data']['transactions'][0]['executed']);
        static::assertTrue($data['data']['deliveries'][0]['executed']);
    }

    public function testAlreadyCancelledOrderReturnsNotExecuted(): void
    {
        $order = $this->buildOrder('cancelled', 'cancelled', 'cancelled');
        $tool = $this->createTool($order, availableActions: []);

        $output = ($tool)(orderNumber: '10001', dryRun: false);
        $data = json_decode($output, true, 512, \JSON_THROW_ON_ERROR);

        static::assertTrue($data['success']);
        static::assertFalse($data['data']['order']['executed']);
        static::assertSame('Already in target state', $data['data']['order']['note']);
        static::assertFalse($data['data']['transactions'][0]['executed']);
        static::assertFalse($data['data']['deliveries'][0]['executed']);
    }

    public function testRefundTransactionsFlagUsesRefundActionForPaidState(): void
    {
        $order = $this->buildOrder('open', 'paid', 'open');
        $tool = $this->createTool($order, availableActions: ['cancel', 'refund']);

        $output = ($tool)(orderNumber: '10001', refundTransactions: true, dryRun: true);
        $data = json_decode($output, true, 512, \JSON_THROW_ON_ERROR);

        static::assertTrue($data['success']);
        static::assertSame('refund', $data['data']['transactions'][0]['action']);
        static::assertSame('refunded', $data['data']['transactions'][0]['to']);
    }

    public function testRefundTransactionsFlagUsesCancelForOpenState(): void
    {
        $order = $this->buildOrder('open', 'open', 'open');
        $tool = $this->createTool($order, availableActions: ['cancel']);

        $output = ($tool)(orderNumber: '10001', refundTransactions: true, dryRun: true);
        $data = json_decode($output, true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame('cancel', $data['data']['transactions'][0]['action']);
    }

    public function testTransitionNotAvailableReturnsNote(): void
    {
        $order = $this->buildOrder('in_progress', 'open', 'open');
        $tool = $this->createTool($order, availableActions: []);

        $output = ($tool)(orderNumber: '10001', dryRun: true);
        $data = json_decode($output, true, 512, \JSON_THROW_ON_ERROR);

        static::assertTrue($data['success']);
        static::assertFalse($data['data']['order']['executed']);
        static::assertStringContainsString('not available', $data['data']['order']['note']);
    }

    public function testGetAvailableTransitionsExceptionTreatsAsUnavailable(): void
    {
        $order = $this->buildOrder('open', 'open', 'open');
        $tool = $this->createTool($order, availableActions: [], throwOnGetTransitions: true);

        $output = ($tool)(orderNumber: '10001', dryRun: true);
        $data = json_decode($output, true, 512, \JSON_THROW_ON_ERROR);

        static::assertTrue($data['success']);
        static::assertFalse($data['data']['order']['executed']);
        static::assertStringContainsString('not available', $data['data']['order']['note']);
    }

    public function testDeniesWritePermissionOnCommit(): void
    {
        $source = new AdminApiSource(null, null);
        $source->setPermissions(['order:read']);
        $context = new Context($source, [], Defaults::CURRENCY, [Defaults::LANGUAGE_SYSTEM]);

        $contextProvider = static::createStub(McpContextProvider::class);
        $contextProvider->method('getContext')->willReturn($context);

        $order = $this->buildOrder('open', 'open', 'open');
        $collection = new OrderCollection([$order]);
        $result = new EntitySearchResult('order', 1, $collection, null, new Criteria(), $context);

        $repository = static::createStub(EntityRepository::class);
        $repository->method('search')->willReturn($result);

        $registry = static::createStub(DefinitionInstanceRegistry::class);
        $registry->method('getRepository')->willReturn($repository);

        $tool = new OrderCancelTool($registry, $contextProvider, static::createStub(StateMachineRegistry::class));
        $output = ($tool)(orderNumber: '10001', dryRun: false);

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

        $tool = new OrderCancelTool($registry, $contextProvider, static::createStub(StateMachineRegistry::class));
        $output = ($tool)(orderNumber: '10001');

        $data = json_decode($output, true, 512, \JSON_THROW_ON_ERROR);

        static::assertFalse($data['success']);
        static::assertStringContainsString('order:read', $data['error']);
    }

    public function testOrderNotFoundReturnsError(): void
    {
        $tool = $this->createTool(null, availableActions: []);

        $output = ($tool)(orderNumber: '99999');
        $data = json_decode($output, true, 512, \JSON_THROW_ON_ERROR);

        static::assertFalse($data['success']);
        static::assertSame('Order not found.', $data['error']);
    }

    public function testLookupByOrderIdUsesIdCriteria(): void
    {
        $order = $this->buildOrder('open', 'open', 'open');
        $tool = $this->createTool($order, availableActions: ['cancel']);

        $output = ($tool)(orderId: $order->getId(), dryRun: true);
        $data = json_decode($output, true, 512, \JSON_THROW_ON_ERROR);

        static::assertTrue($data['success']);
        static::assertSame('cancel', $data['data']['order']['action']);
    }

    public function testNoIdentifierReturnsError(): void
    {
        $tool = $this->createTool(null, availableActions: []);

        $output = ($tool)();
        $data = json_decode($output, true, 512, \JSON_THROW_ON_ERROR);

        static::assertFalse($data['success']);
        static::assertStringContainsString('orderNumber or orderId', $data['error']);
    }

    /**
     * @param list<string> $availableActions
     */
    private function createTool(
        ?OrderEntity $order,
        array $availableActions,
        bool $executeTransitions = false,
        bool $throwOnGetTransitions = false,
    ): OrderCancelTool {
        $context = Context::createDefaultContext();

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
                $transitions[] = $transition;
            }

            $stateMachineRegistry->method('getAvailableTransitions')->willReturn($transitions);
        }

        if ($executeTransitions) {
            $stateCollection = new StateMachineStateCollection();
            $stateMachineRegistry->method('transition')->willReturn($stateCollection);
        }

        return new OrderCancelTool($registry, $contextProvider, $stateMachineRegistry);
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
