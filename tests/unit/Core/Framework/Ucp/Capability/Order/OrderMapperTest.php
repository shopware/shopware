<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Ucp\Capability\Order;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTax;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderCustomer\OrderCustomerEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Ucp\Capability\Order\OrderMapper;
use Shopware\Core\System\Currency\CurrencyEntity;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateEntity;
use Shopware\Core\System\StateMachine\StateMachineEntity;

/**
 * Pins the UCP order wire-format. The mapper is the single transformer between
 * Shopware order entities and the platform-facing order response.
 *
 * @internal
 */
#[CoversClass(OrderMapper::class)]
class OrderMapperTest extends TestCase
{
    public function testMapsBasicOrderWithLineItemsAndTotals(): void
    {
        $order = $this->makeOrder('order-1', 'SW10001', state: 'open', amount: 49.98);
        $order->setLineItems(new OrderLineItemCollection([
            $this->lineItem('li-1', 'Widget', productNumber: 'SKU-1', unit: 19.99, qty: 2),
        ]));

        $payload = (new OrderMapper())->toResponse($order, 'https://shop.example/');

        static::assertSame('order-1', $payload['id']);
        static::assertSame('SW10001', $payload['order_number']);
        static::assertSame('open', $payload['status']);
        static::assertSame('EUR', $payload['currency']);
        static::assertSame('https://shop.example/account/order/order-1', $payload['permalink_url']);
        static::assertCount(1, $payload['line_items']);
        static::assertSame('SKU-1', $payload['line_items'][0]['item']['id']);
        static::assertSame(1999, $payload['line_items'][0]['item']['price']);

        // quantity is structured per order-rest.md
        static::assertSame(
            ['original' => 2, 'total' => 2, 'fulfilled' => 0],
            $payload['line_items'][0]['quantity']
        );
    }

    public function testFallsBackToExampleInvalidPermalinkWhenStorefrontBaseIsNull(): void
    {
        $order = $this->makeOrder('order-2', 'SW2', state: 'open');
        $payload = (new OrderMapper())->toResponse($order, null);

        static::assertStringStartsWith('https://example.invalid/orders/', $payload['permalink_url']);
    }

    public function testUsesDeepLinkCodeInPermalinkWhenAvailable(): void
    {
        $order = $this->makeOrder('order-3', 'SW3', state: 'open');
        $order->setDeepLinkCode('deep-link-token');

        $payload = (new OrderMapper())->toResponse($order, 'https://shop.example');

        static::assertSame('https://shop.example/account/order/deep-link-token', $payload['permalink_url']);
    }

    /**
     * @param array{shopware:string,ucp:string} $case
     */
    #[DataProvider('statusMappingProvider')]
    public function testMapsShopwareStateToUcpStatus(array $case): void
    {
        $order = $this->makeOrder('o', 'O', state: $case['shopware']);
        $payload = (new OrderMapper())->toResponse($order);
        static::assertSame($case['ucp'], $payload['status']);
    }

    /**
     * @return iterable<string, array{0: array{shopware: string, ucp: string}}>
     */
    public static function statusMappingProvider(): iterable
    {
        yield 'open' => [['shopware' => 'open', 'ucp' => 'open']];
        yield 'completed' => [['shopware' => 'completed', 'ucp' => 'completed']];
        yield 'cancelled_uk' => [['shopware' => 'cancelled', 'ucp' => 'canceled']];
        yield 'canceled_us' => [['shopware' => 'canceled', 'ucp' => 'canceled']];
        yield 'in_progress' => [['shopware' => 'in_progress', 'ucp' => 'in_progress']];
        yield 'refunded' => [['shopware' => 'refunded', 'ucp' => 'refunded']];
        yield 'custom_state_passes_through' => [['shopware' => 'custom_review_pending', 'ucp' => 'custom_review_pending']];
    }

    public function testFallsBackToOpenWhenStateMachineStateIsMissing(): void
    {
        $order = new OrderEntity();
        $order->setId('o-noState');
        $order->setOrderNumber('S-1');
        $order->setOrderDateTime(new \DateTimeImmutable('2026-05-20T12:00:00Z'));
        $order->setPrice(new CartPrice(0, 0, 0, new CalculatedTaxCollection(), new TaxRuleCollection(), CartPrice::TAX_STATE_GROSS));
        $order->setAmountTotal(0);
        $order->setPositionPrice(0);

        $payload = (new OrderMapper())->toResponse($order);
        static::assertSame('open', $payload['status']);
    }

    public function testMapsBuyerFromOrderCustomer(): void
    {
        $order = $this->makeOrder('o', 'O', state: 'open');
        $oc = new OrderCustomerEntity();
        $oc->setEmail('jane@example.com');
        $oc->setFirstName('Jane');
        $oc->setLastName('Doe');
        $order->setOrderCustomer($oc);

        $payload = (new OrderMapper())->toResponse($order);
        static::assertSame(
            ['email' => 'jane@example.com', 'first_name' => 'Jane', 'last_name' => 'Doe'],
            $payload['buyer']
        );
    }

    public function testUsesUcpCheckoutIdFromCustomFieldsWhenPresent(): void
    {
        $order = $this->makeOrder('o', 'O', state: 'open');
        $order->setCustomFields(['ucp_checkout_id' => 'ck_abc']);

        $payload = (new OrderMapper())->toResponse($order);

        static::assertSame('ck_abc', $payload['checkout_id']);
    }

    public function testCheckoutIdDefaultsToOrderIdWhenNoCustomField(): void
    {
        $order = $this->makeOrder('o-42', 'O', state: 'open');
        $payload = (new OrderMapper())->toResponse($order);

        static::assertSame('o-42', $payload['checkout_id']);
    }

    public function testFulfillmentEmitsShippedEventForShippedDelivery(): void
    {
        $order = $this->makeOrder('o', 'O', state: 'in_progress');
        $order->setLineItems(new OrderLineItemCollection([
            $this->lineItem('li-1', 'X', productNumber: 'X', unit: 10.0, qty: 1),
        ]));

        $delivery = new OrderDeliveryEntity();
        $delivery->setId('del-1');
        $delivery->setShippingDateEarliest(new \DateTimeImmutable('2026-05-21T00:00:00Z'));
        $delivery->setShippingDateLatest(new \DateTimeImmutable('2026-05-23T00:00:00Z'));
        $delivery->setCreatedAt(new \DateTimeImmutable('2026-05-20T10:00:00Z'));
        $delivery->setUpdatedAt(new \DateTimeImmutable('2026-05-22T15:00:00Z'));
        $delivery->setStateMachineState($this->state('shipped'));
        $order->setDeliveries(new OrderDeliveryCollection([$delivery]));

        $payload = (new OrderMapper())->toResponse($order);

        static::assertCount(1, $payload['fulfillment']['expectations']);
        static::assertCount(1, $payload['fulfillment']['events']);
        static::assertSame('shipped', $payload['fulfillment']['events'][0]['type']);
        static::assertSame('del-1_shipped', $payload['fulfillment']['events'][0]['id']);
    }

    public function testFulfillmentHasEmptyEventsForOpenDelivery(): void
    {
        $order = $this->makeOrder('o', 'O', state: 'open');
        $order->setLineItems(new OrderLineItemCollection([]));

        $delivery = new OrderDeliveryEntity();
        $delivery->setId('del-2');
        $delivery->setShippingDateEarliest(new \DateTimeImmutable('2026-05-21T00:00:00Z'));
        $delivery->setShippingDateLatest(new \DateTimeImmutable('2026-05-23T00:00:00Z'));
        $delivery->setStateMachineState($this->state('open'));
        $order->setDeliveries(new OrderDeliveryCollection([$delivery]));

        $payload = (new OrderMapper())->toResponse($order);
        static::assertSame([], $payload['fulfillment']['events']);
    }

    public function testToUcpOrderIsAliasForToResponse(): void
    {
        $order = $this->makeOrder('o', 'O', state: 'open');
        $mapper = new OrderMapper();

        static::assertSame($mapper->toResponse($order, null), $mapper->toUcpOrder($order, null));
    }

    private function makeOrder(string $id, string $orderNumber, string $state, float $amount = 0.0): OrderEntity
    {
        $order = new OrderEntity();
        $order->setId($id);
        $order->setOrderNumber($orderNumber);
        $order->setOrderDateTime(new \DateTimeImmutable('2026-05-20T12:00:00Z'));
        $order->setAmountTotal($amount);
        $order->setPositionPrice($amount);

        $taxes = new CalculatedTaxCollection([new CalculatedTax(0, 19, 0)]);
        $order->setPrice(new CartPrice(
            $amount,
            $amount,
            $amount,
            $taxes,
            new TaxRuleCollection(),
            CartPrice::TAX_STATE_GROSS
        ));

        $order->setStateMachineState($this->state($state));

        $currency = new CurrencyEntity();
        $currency->setId('eur');
        $currency->setIsoCode('EUR');
        $order->setCurrency($currency);

        return $order;
    }

    private function lineItem(string $id, string $label, string $productNumber, float $unit, int $qty): OrderLineItemEntity
    {
        $line = new OrderLineItemEntity();
        $line->setId($id);
        $line->setLabel($label);
        $line->setUnitPrice($unit);
        $line->setTotalPrice($unit * $qty);
        $line->setQuantity($qty);
        $line->setPayload(['productNumber' => $productNumber]);
        $line->setPrice(new CalculatedPrice(
            $unit,
            $unit * $qty,
            new CalculatedTaxCollection([new CalculatedTax(round($unit * $qty * 0.19, 2), 19, $unit * $qty)]),
            new TaxRuleCollection()
        ));

        return $line;
    }

    private function state(string $technicalName): StateMachineStateEntity
    {
        $state = new StateMachineStateEntity();
        $state->setId('state-' . $technicalName);
        $state->setTechnicalName($technicalName);
        $state->setName($technicalName);
        $state->setStateMachine(new StateMachineEntity());

        return $state;
    }
}
