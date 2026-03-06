<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Mcp\Tool;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderCustomer\OrderCustomerEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
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
use Shopware\Core\Framework\Mcp\Tool\OrderSummaryTool;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Currency\CurrencyEntity;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateEntity;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(OrderSummaryTool::class)]
class OrderSummaryToolTest extends TestCase
{
    public function testLookupByOrderNumber(): void
    {
        $orderId = Uuid::randomHex();
        $order = $this->buildOrder($orderId, '10001');

        $tool = $this->createTool($order);
        $output = ($tool)(orderNumber: '10001');

        $data = json_decode($output, true, 512, \JSON_THROW_ON_ERROR);

        static::assertTrue($data['success']);
        static::assertSame($orderId, $data['data']['id']);
        static::assertSame('10001', $data['data']['orderNumber']);
        static::assertSame('open', $data['data']['status']);
        static::assertSame(99.99, $data['data']['amountTotal']);
        static::assertSame('EUR', $data['data']['currencyIsoCode']);
        static::assertSame('john@example.com', $data['data']['customer']['email']);
        static::assertCount(1, $data['data']['lineItems']);
        static::assertSame('T-Shirt', $data['data']['lineItems'][0]['label']);
        static::assertCount(1, $data['data']['transactions']);
        static::assertSame('open', $data['data']['transactions'][0]['status']);
        static::assertCount(1, $data['data']['deliveries']);
    }

    public function testLookupByOrderId(): void
    {
        $orderId = Uuid::randomHex();
        $order = $this->buildOrder($orderId, '10002');

        $tool = $this->createTool($order);
        $output = ($tool)(orderId: $orderId);

        $data = json_decode($output, true, 512, \JSON_THROW_ON_ERROR);

        static::assertTrue($data['success']);
        static::assertSame($orderId, $data['data']['id']);
    }

    public function testOrderIdTakesPriorityWhenBothProvided(): void
    {
        $orderId = Uuid::randomHex();
        $order = $this->buildOrder($orderId, '10001');

        $repository = $this->createMock(EntityRepository::class);
        $repository->method('search')->willReturnCallback(function (Criteria $criteria, Context $context) use ($order, $orderId): EntitySearchResult {
            static::assertSame([$orderId], $criteria->getIds());
            static::assertEmpty($criteria->getFilters(), 'orderNumber filter must not be applied when orderId is set');

            $collection = new OrderCollection([$order]);

            return new EntitySearchResult('order', 1, $collection, null, $criteria, $context);
        });

        $registry = $this->createMock(DefinitionInstanceRegistry::class);
        $registry->method('getRepository')->with('order')->willReturn($repository);

        $contextProvider = $this->createMock(McpContextProvider::class);
        $contextProvider->method('getContext')->willReturn(Context::createDefaultContext());

        $tool = new OrderSummaryTool($registry, $contextProvider);
        $output = ($tool)(orderNumber: '99999', orderId: $orderId);

        $data = json_decode($output, true, 512, \JSON_THROW_ON_ERROR);

        static::assertTrue($data['success']);
        static::assertSame($orderId, $data['data']['id']);
    }

    public function testNotFoundReturnsError(): void
    {
        $tool = $this->createTool(null);
        $output = ($tool)(orderNumber: '99999');

        $data = json_decode($output, true, 512, \JSON_THROW_ON_ERROR);

        static::assertFalse($data['success']);
        static::assertSame('Order not found.', $data['error']);
    }

    public function testNoIdentifierReturnsError(): void
    {
        $tool = $this->createTool(null);
        $output = ($tool)();

        $data = json_decode($output, true, 512, \JSON_THROW_ON_ERROR);

        static::assertFalse($data['success']);
        static::assertStringContainsString('orderNumber or orderId', $data['error']);
    }

    public function testDeniesAccessWithoutPermission(): void
    {
        $source = new AdminApiSource(null, null);
        $source->setPermissions([]);
        $context = new Context($source, [], Defaults::CURRENCY, [Defaults::LANGUAGE_SYSTEM]);

        $registry = $this->createMock(DefinitionInstanceRegistry::class);
        $registry->expects($this->never())->method('getRepository');

        $contextProvider = $this->createMock(McpContextProvider::class);
        $contextProvider->method('getContext')->willReturn($context);

        $tool = new OrderSummaryTool($registry, $contextProvider);
        $output = ($tool)(orderNumber: '10001');

        $data = json_decode($output, true, 512, \JSON_THROW_ON_ERROR);

        static::assertFalse($data['success']);
        static::assertStringContainsString('order:read', $data['error']);
    }

    private function createTool(?OrderEntity $order): OrderSummaryTool
    {
        $context = Context::createDefaultContext();

        $collection = new OrderCollection();

        if ($order !== null) {
            $collection->add($order);
        }

        $result = new EntitySearchResult(
            'order',
            $collection->count(),
            $collection,
            null,
            new Criteria(),
            $context,
        );

        $repository = $this->createMock(EntityRepository::class);
        $repository->method('search')->willReturn($result);

        $registry = $this->createMock(DefinitionInstanceRegistry::class);
        $registry->method('getRepository')->with('order')->willReturn($repository);

        $contextProvider = $this->createMock(McpContextProvider::class);
        $contextProvider->method('getContext')->willReturn($context);

        return new OrderSummaryTool($registry, $contextProvider);
    }

    private function buildOrder(string $id, string $orderNumber): OrderEntity
    {
        $state = new StateMachineStateEntity();
        $state->setId(Uuid::randomHex());
        $state->setTechnicalName('open');
        $state->setUniqueIdentifier(Uuid::randomHex());

        $currency = new CurrencyEntity();
        $currency->setId(Defaults::CURRENCY);
        $currency->setIsoCode('EUR');
        $currency->setUniqueIdentifier(Defaults::CURRENCY);

        $customer = new OrderCustomerEntity();
        $customer->setId(Uuid::randomHex());
        $customer->setEmail('john@example.com');
        $customer->setFirstName('John');
        $customer->setLastName('Doe');
        $customer->setUniqueIdentifier(Uuid::randomHex());

        $lineItem = new OrderLineItemEntity();
        $lineItem->setId(Uuid::randomHex());
        $lineItem->setIdentifier(Uuid::randomHex());
        $lineItem->setLabel('T-Shirt');
        $lineItem->setQuantity(2);
        $lineItem->setUnitPrice(49.99);
        $lineItem->setTotalPrice(99.98);
        $lineItem->setUniqueIdentifier(Uuid::randomHex());

        $txState = new StateMachineStateEntity();
        $txState->setId(Uuid::randomHex());
        $txState->setTechnicalName('open');
        $txState->setUniqueIdentifier(Uuid::randomHex());

        $transaction = new OrderTransactionEntity();
        $transaction->setId(Uuid::randomHex());
        $transaction->setStateMachineState($txState);
        $transaction->setAmount(new CalculatedPrice(99.99, 99.99, new CalculatedTaxCollection(), new TaxRuleCollection()));
        $transaction->setUniqueIdentifier(Uuid::randomHex());

        $deliveryState = new StateMachineStateEntity();
        $deliveryState->setId(Uuid::randomHex());
        $deliveryState->setTechnicalName('open');
        $deliveryState->setUniqueIdentifier(Uuid::randomHex());

        $delivery = new OrderDeliveryEntity();
        $delivery->setId(Uuid::randomHex());
        $delivery->setStateMachineState($deliveryState);
        $delivery->setUniqueIdentifier(Uuid::randomHex());

        $order = new OrderEntity();
        $order->setId($id);
        $order->setOrderNumber($orderNumber);
        $order->setOrderDateTime(new \DateTimeImmutable('2025-03-01T10:00:00+00:00'));
        $order->setStateMachineState($state);
        $order->setAmountTotal(99.99);
        $order->setAmountNet(83.99);
        $order->setShippingTotal(4.99);
        $order->setCurrency($currency);
        $order->setOrderCustomer($customer);
        $order->setLineItems(new OrderLineItemCollection([$lineItem]));
        $order->setTransactions(new OrderTransactionCollection([$transaction]));
        $order->setDeliveries(new OrderDeliveryCollection([$delivery]));
        $order->setUniqueIdentifier($id);

        return $order;
    }
}
