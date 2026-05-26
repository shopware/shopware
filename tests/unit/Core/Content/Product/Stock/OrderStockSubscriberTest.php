<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\Stock;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemDefinition;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Checkout\Order\OrderStates;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\Stock\OrderStockSubscriber;
use Shopware\Core\Content\Product\Stock\StockAlteration;
use Shopware\Core\Content\Product\Stock\StockStorage;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWriteEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\DeleteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\InsertCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\UpdateCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateEntity;
use Shopware\Core\System\StateMachine\Event\StateMachineTransitionEvent;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[CoversClass(OrderStockSubscriber::class)]
class OrderStockSubscriberTest extends TestCase
{
    private IdsCollection $ids;

    private EntityDefinition $definition;

    private Connection&Stub $connection;

    private StockStorage&MockObject $stockStorage;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();
        $this->connection = static::createStub(Connection::class);
        $this->stockStorage = static::createMock(StockStorage::class);

        // Compiles the definition: WriteCommand constructors need getPrimaryKeys()
        // which only works after registration in a DefinitionInstanceRegistry.
        new StaticDefinitionInstanceRegistry(
            [$this->definition = new OrderLineItemDefinition()],
            static::createStub(ValidatorInterface::class),
            static::createStub(EntityWriteGatewayInterface::class),
        );
    }

    #[TestDox('subscribes to state machine transitions and entity write events')]
    public function testGetSubscribedEvents(): void
    {
        $events = OrderStockSubscriber::getSubscribedEvents();

        static::assertArrayHasKey(StateMachineTransitionEvent::class, $events);
        static::assertArrayHasKey(EntityWriteEvent::class, $events);
    }

    #[TestDox('does not alter stock when stock management is disabled')]
    public function testBeforeWriteCanBeDisabled(): void
    {
        $context = Context::createDefaultContext()->createWithVersionId($this->ids->create('version'));

        $this->stockStorage->expects($this->never())->method('alter');

        $stockSubscriber = new OrderStockSubscriber($this->connection, $this->stockStorage, false);

        $event = EntityWriteEvent::create(WriteContext::createFromContext($context), []);
        $stockSubscriber->beforeWriteOrderItems($event);
        $event->success();
    }

    #[TestDox('ignores order line item writes on non-live versions')]
    public function testBeforeWriteOnlyReactsToLiveVersions(): void
    {
        $context = Context::createDefaultContext()->createWithVersionId($this->ids->create('version'));

        $this->stockStorage->expects($this->never())->method('alter');

        $stockSubscriber = new OrderStockSubscriber($this->connection, $this->stockStorage, true);

        $event = EntityWriteEvent::create(WriteContext::createFromContext($context), []);
        $stockSubscriber->beforeWriteOrderItems($event);
        $event->success();
    }

    #[TestDox('ignores writes to entities other than order line items')]
    public function testBeforeWriteOnlyReactsToOrderLineItems(): void
    {
        $context = Context::createDefaultContext();

        $this->stockStorage->expects($this->never())->method('alter');

        $stockSubscriber = new OrderStockSubscriber($this->connection, $this->stockStorage, true);

        new StaticDefinitionInstanceRegistry(
            [$productDefinition = new ProductDefinition()],
            static::createStub(ValidatorInterface::class),
            static::createStub(EntityWriteGatewayInterface::class),
        );

        $event = EntityWriteEvent::create(WriteContext::createFromContext($context), [
            new DeleteCommand($productDefinition, ['id' => $this->ids->getBytes('item-1')], $this->buildExistence($this->ids->get('item-1'), true)),
        ]);
        $stockSubscriber->beforeWriteOrderItems($event);
        $event->success();
    }

    #[TestDox('ignores updates that do not change product or quantity')]
    public function testBeforeWriteOnlyReactsToProductAndQuantityChanges(): void
    {
        $context = Context::createDefaultContext();

        $this->stockStorage->expects($this->never())->method('alter');

        $stockSubscriber = new OrderStockSubscriber($this->connection, $this->stockStorage, true);

        $event = EntityWriteEvent::create(WriteContext::createFromContext($context), [
            new UpdateCommand($this->definition, ['some-field' => 'some-value'], ['id' => $this->ids->getBytes('item-1')], $this->buildExistence($this->ids->get('item-1'), true), '/0'),
        ]);
        $stockSubscriber->beforeWriteOrderItems($event);
        $event->success();
    }

    #[TestDox('increases stock when new order line items are inserted')]
    public function testInsertedOrderItemsUpdateStock(): void
    {
        $item1 = $this->ids->get('item-1');
        $item2 = $this->ids->get('item-2');
        $product1 = $this->ids->get('product-1');
        $product2 = $this->ids->get('product-2');

        $this->assertOrderItemStockChanges(
            beforeState: [],
            afterState: [
                $item1 => ['id' => $item1, 'quantity' => '10', 'referenced_id' => $product1],
                $item2 => ['id' => $item2, 'quantity' => '10', 'referenced_id' => $product2],
            ],
            expectedUpdates: [
                ['lineItemId' => $item1, 'productId' => $product1, 'quantityBefore' => 0, 'newQuantity' => 10],
                ['lineItemId' => $item2, 'productId' => $product2, 'quantityBefore' => 0, 'newQuantity' => 10],
            ],
            commands: [
                new InsertCommand($this->definition, [], ['id' => $this->ids->getBytes('item-1')], $this->buildExistence($item1, false), '/0'),
                new InsertCommand($this->definition, [], ['id' => $this->ids->getBytes('item-2')], $this->buildExistence($item2, false), '/0'),
            ],
        );
    }

    #[TestDox('decreases stock when order line items are deleted')]
    public function testDeletedOrderItemsUpdateStock(): void
    {
        $item1 = $this->ids->get('item-1');
        $item2 = $this->ids->get('item-2');
        $product1 = $this->ids->get('product-1');
        $product2 = $this->ids->get('product-2');

        $this->assertOrderItemStockChanges(
            beforeState: [
                $item1 => ['id' => $item1, 'quantity' => '10', 'referenced_id' => $product1],
                $item2 => ['id' => $item2, 'quantity' => '10', 'referenced_id' => $product2],
            ],
            afterState: [],
            expectedUpdates: [
                ['lineItemId' => $item1, 'productId' => $product1, 'quantityBefore' => 10, 'newQuantity' => 0],
                ['lineItemId' => $item2, 'productId' => $product2, 'quantityBefore' => 10, 'newQuantity' => 0],
            ],
            commands: [
                new DeleteCommand($this->definition, ['id' => $this->ids->getBytes('item-1')], $this->buildExistence($item1, true)),
                new DeleteCommand($this->definition, ['id' => $this->ids->getBytes('item-2')], $this->buildExistence($item2, true)),
            ],
        );
    }

    #[TestDox('handles mixed insert and delete of order line items in a single write')]
    public function testInsertAndDeleteOrderItemsUpdateStock(): void
    {
        $item1 = $this->ids->get('item-1');
        $item2 = $this->ids->get('item-2');
        $product1 = $this->ids->get('product-1');
        $product2 = $this->ids->get('product-2');

        $this->assertOrderItemStockChanges(
            beforeState: [
                $item1 => ['id' => $item1, 'quantity' => '10', 'referenced_id' => $product1],
            ],
            afterState: [
                $item2 => ['id' => $item2, 'quantity' => '10', 'referenced_id' => $product2],
            ],
            expectedUpdates: [
                ['lineItemId' => $item1, 'productId' => $product1, 'quantityBefore' => 10, 'newQuantity' => 0],
                ['lineItemId' => $item2, 'productId' => $product2, 'quantityBefore' => 0, 'newQuantity' => 10],
            ],
            commands: [
                new DeleteCommand($this->definition, ['id' => $this->ids->getBytes('item-1')], $this->buildExistence($item1, true)),
                new InsertCommand($this->definition, [], ['id' => $this->ids->getBytes('item-2')], $this->buildExistence($item2, false), '/0'),
            ],
        );
    }

    #[TestDox('adjusts stock when order line item quantity is updated')]
    public function testUpdatedQuantityUpdatesStock(): void
    {
        $item1 = $this->ids->get('item-1');
        $item2 = $this->ids->get('item-2');
        $product1 = $this->ids->get('product-1');
        $product2 = $this->ids->get('product-2');

        $this->assertOrderItemStockChanges(
            beforeState: [
                $item1 => ['id' => $item1, 'quantity' => '10', 'referenced_id' => $product1],
                $item2 => ['id' => $item2, 'quantity' => '5', 'referenced_id' => $product2],
            ],
            afterState: [
                $item1 => ['id' => $item1, 'quantity' => '20', 'referenced_id' => $product1],
                $item2 => ['id' => $item2, 'quantity' => '3', 'referenced_id' => $product2],
            ],
            expectedUpdates: [
                ['lineItemId' => $item1, 'productId' => $product1, 'quantityBefore' => 10, 'newQuantity' => 20],
                ['lineItemId' => $item2, 'productId' => $product2, 'quantityBefore' => 5, 'newQuantity' => 3],
            ],
            commands: [
                new UpdateCommand($this->definition, ['quantity' => 20], ['id' => $this->ids->getBytes('item-1')], $this->buildExistence($item1, true), '/0'),
                new UpdateCommand($this->definition, ['quantity' => 3], ['id' => $this->ids->getBytes('item-2')], $this->buildExistence($item2, true), '/0'),
            ],
        );
    }

    #[TestDox('adjusts stock when order line item product is changed')]
    public function testUpdatedProductUpdatesStock(): void
    {
        $item1 = $this->ids->get('item-1');
        $product1 = $this->ids->get('product-1');
        $product2 = $this->ids->get('product-2');

        $this->assertOrderItemStockChanges(
            beforeState: [
                $item1 => ['id' => $item1, 'quantity' => '10', 'referenced_id' => $product1],
            ],
            afterState: [
                $item1 => ['id' => $item1, 'quantity' => '10', 'referenced_id' => $product2],
            ],
            expectedUpdates: [
                ['lineItemId' => $item1, 'productId' => $product1, 'quantityBefore' => 10, 'newQuantity' => 0],
                ['lineItemId' => $item1, 'productId' => $product2, 'quantityBefore' => 0, 'newQuantity' => 10],
            ],
            commands: [
                new UpdateCommand($this->definition, ['referenced_id' => $product2], ['id' => $this->ids->getBytes('item-1')], $this->buildExistence($item1, true), '/0'),
            ],
        );
    }

    #[TestDox('adjusts stock when both product and quantity are changed')]
    public function testUpdatedProductAndQuantityUpdatesStock(): void
    {
        $item1 = $this->ids->get('item-1');
        $product1 = $this->ids->get('product-1');
        $product2 = $this->ids->get('product-2');

        $this->assertOrderItemStockChanges(
            beforeState: [
                $item1 => ['id' => $item1, 'quantity' => '10', 'referenced_id' => $product1],
            ],
            afterState: [
                $item1 => ['id' => $item1, 'quantity' => '15', 'referenced_id' => $product2],
            ],
            expectedUpdates: [
                ['lineItemId' => $item1, 'productId' => $product1, 'quantityBefore' => 10, 'newQuantity' => 0],
                ['lineItemId' => $item1, 'productId' => $product2, 'quantityBefore' => 0, 'newQuantity' => 15],
            ],
            commands: [
                new UpdateCommand($this->definition, ['quantity' => 15, 'referenced_id' => $product2], ['id' => $this->ids->getBytes('item-1')], $this->buildExistence($item1, true), '/0'),
            ],
        );
    }

    #[TestDox('does not alter stock on state change when stock management is disabled')]
    public function testStateChangeCanBeDisabled(): void
    {
        $context = Context::createDefaultContext()->createWithVersionId($this->ids->create('version'));

        $fromState = new StateMachineStateEntity();
        $fromState->setTechnicalName(OrderStates::STATE_OPEN);

        $toState = new StateMachineStateEntity();
        $toState->setTechnicalName(OrderStates::STATE_CANCELLED);

        $event = new StateMachineTransitionEvent(OrderDefinition::ENTITY_NAME, $this->ids->get('order-1'), $fromState, $toState, $context);

        $this->stockStorage->expects($this->never())->method('alter');

        $stockSubscriber = new OrderStockSubscriber($this->connection, $this->stockStorage, false);

        $stockSubscriber->stateChanged($event);
    }

    #[TestDox('ignores state changes on non-live versions')]
    public function testStateChangeOnlyReactsToLiveVersions(): void
    {
        $context = Context::createDefaultContext()->createWithVersionId($this->ids->create('version'));

        $fromState = new StateMachineStateEntity();
        $fromState->setTechnicalName(OrderStates::STATE_OPEN);

        $toState = new StateMachineStateEntity();
        $toState->setTechnicalName(OrderStates::STATE_CANCELLED);

        $event = new StateMachineTransitionEvent(OrderDefinition::ENTITY_NAME, $this->ids->get('order-1'), $fromState, $toState, $context);

        $this->stockStorage->expects($this->never())->method('alter');

        $stockSubscriber = new OrderStockSubscriber($this->connection, $this->stockStorage, true);

        $stockSubscriber->stateChanged($event);
    }

    #[TestDox('ignores state changes on entities other than orders')]
    public function testStateChangeOnlyReactsToOrderEntities(): void
    {
        $context = Context::createDefaultContext();

        $fromState = new StateMachineStateEntity();
        $fromState->setTechnicalName(OrderStates::STATE_OPEN);

        $toState = new StateMachineStateEntity();
        $toState->setTechnicalName(OrderStates::STATE_CANCELLED);

        $event = new StateMachineTransitionEvent('wrong-entity', $this->ids->get('order-1'), $fromState, $toState, $context);

        $this->stockStorage->expects($this->never())->method('alter');

        $stockSubscriber = new OrderStockSubscriber($this->connection, $this->stockStorage, true);
        $stockSubscriber->stateChanged($event);
    }

    #[DataProvider('orderStateTransitionProvider')]
    #[TestDox('adjusts stock when order transitions between open and cancelled states')]
    public function testStocksAreUpdatedWhenOrdersTransitionThroughStates(
        string $fromStateName,
        string $toStateName,
        int $quantityBefore,
        int $quantityAfter
    ): void {
        $context = Context::createDefaultContext();

        $fromState = new StateMachineStateEntity();
        $fromState->setTechnicalName($fromStateName);

        $toState = new StateMachineStateEntity();
        $toState->setTechnicalName($toStateName);

        $item1 = $this->ids->get('item-1');
        $item2 = $this->ids->get('item-2');
        $product1 = $this->ids->get('product-1');
        $product2 = $this->ids->get('product-2');

        $event = new StateMachineTransitionEvent(OrderDefinition::ENTITY_NAME, $this->ids->get('order-1'), $fromState, $toState, $context);

        $this->connection->method('fetchAllAssociative')->willReturn([
            ['id' => $item1, 'quantity' => '10', 'product_id' => $product1],
            ['id' => $item2, 'quantity' => '10', 'product_id' => $product2],
        ]);

        $this->stockStorage->expects($this->once())
            ->method('alter')
            ->with(static::callback(static function (array $changes) use ($item1, $item2, $product1, $product2, $quantityBefore, $quantityAfter) {
                static::assertCount(2, $changes);
                static::assertInstanceOf(StockAlteration::class, $changes[0]);
                static::assertInstanceOf(StockAlteration::class, $changes[1]);

                static::assertSame($item1, $changes[0]->lineItemId);
                static::assertSame($product1, $changes[0]->productId);
                static::assertSame($quantityBefore, $changes[0]->quantityBefore);
                static::assertSame($quantityAfter, $changes[0]->newQuantity);

                static::assertSame($item2, $changes[1]->lineItemId);
                static::assertSame($product2, $changes[1]->productId);
                static::assertSame($quantityBefore, $changes[1]->quantityBefore);
                static::assertSame($quantityAfter, $changes[1]->newQuantity);

                return true;
            }));

        $stockSubscriber = new OrderStockSubscriber($this->connection, $this->stockStorage, true);

        $stockSubscriber->stateChanged($event);
    }

    /**
     * @return iterable<string, array{fromStateName: string, toStateName: string, quantityBefore: int, quantityAfter: int}>
     */
    public static function orderStateTransitionProvider(): iterable
    {
        yield 'order-cancelled' => [
            'fromStateName' => OrderStates::STATE_OPEN,
            'toStateName' => OrderStates::STATE_CANCELLED,
            'quantityBefore' => 10,
            'quantityAfter' => 0,
        ];
        yield 'order-reopened' => [
            'fromStateName' => OrderStates::STATE_CANCELLED,
            'toStateName' => OrderStates::STATE_OPEN,
            'quantityBefore' => 0,
            'quantityAfter' => 10,
        ];
    }

    /**
     * @param array<string, array{id: string, quantity: string, referenced_id: string}> $beforeState
     * @param array<string, array{id: string, quantity: string, referenced_id: string}> $afterState
     * @param list<array{lineItemId: string, productId: string, quantityBefore: int, newQuantity: int}> $expectedUpdates
     * @param list<WriteCommand> $commands
     */
    private function assertOrderItemStockChanges(array $beforeState, array $afterState, array $expectedUpdates, array $commands): void
    {
        $this->connection->method('fetchAllAssociativeIndexed')->willReturnOnConsecutiveCalls($beforeState, $afterState);

        $this->stockStorage->expects($this->once())
            ->method('alter')
            ->with(static::callback(static function (array $changes) use ($expectedUpdates): bool {
                static::assertSameSize($expectedUpdates, $changes);

                foreach ($expectedUpdates as $i => $expectedUpdate) {
                    static::assertInstanceOf(StockAlteration::class, $changes[$i]);
                    static::assertSame($expectedUpdate['lineItemId'], $changes[$i]->lineItemId);
                    static::assertSame($expectedUpdate['productId'], $changes[$i]->productId);
                    static::assertSame($expectedUpdate['quantityBefore'], $changes[$i]->quantityBefore);
                    static::assertSame($expectedUpdate['newQuantity'], $changes[$i]->newQuantity);
                }

                return true;
            }));

        $stockSubscriber = new OrderStockSubscriber($this->connection, $this->stockStorage, true);

        $event = EntityWriteEvent::create(WriteContext::createFromContext(Context::createDefaultContext()), $commands);
        $stockSubscriber->beforeWriteOrderItems($event);
        $event->success();
    }

    private function buildExistence(string $id, bool $exists): EntityExistence
    {
        return new EntityExistence(OrderLineItemDefinition::ENTITY_NAME, ['id' => $id], $exists, false, false, []);
    }
}
