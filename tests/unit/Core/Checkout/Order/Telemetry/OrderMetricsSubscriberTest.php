<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Order\Telemetry;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Event\CheckoutOrderPlacedEvent;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Order\Telemetry\OrderMetricsSubscriber;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Telemetry\Metrics\Meter;
use Shopware\Core\Framework\Telemetry\Metrics\Metric\ConfiguredMetric;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Core\System\SalesChannel\Telemetry\SalesChannelTypeResolver;
use Shopware\Core\Test\Stub\Framework\IdsCollection;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(OrderMetricsSubscriber::class)]
class OrderMetricsSubscriberTest extends TestCase
{
    /**
     * @var list<ConfiguredMetric>
     */
    private array $emitted = [];

    private readonly IdsCollection $ids;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();
    }

    public function testSubscribesToCheckoutOrderPlacedEvent(): void
    {
        static::assertSame(
            [
                CheckoutOrderPlacedEvent::class => 'emitOrderPlacedMetrics',
            ],
            OrderMetricsSubscriber::getSubscribedEvents()
        );
    }

    public function testEmitsPlacedCountAndLineItemsWithResolvedLabels(): void
    {
        $order = $this->createOrder(lineItems: 3, paymentTechnicalName: 'payment_invoicepayment');

        $this->createSubscriber()->emitOrderPlacedMetrics($this->createEvent($order));

        static::assertCount(2, $this->emitted);

        $placed = $this->getMetric('order.placed.count');
        static::assertSame(1, $placed->value);
        static::assertSame(['sales_channel_type' => 'storefront', 'payment_method' => 'payment_invoicepayment'], $placed->labels);

        $lineItems = $this->getMetric('order.line_items.count');
        static::assertSame(3, $lineItems->value);
        static::assertSame(['sales_channel_type' => 'storefront'], $lineItems->labels);
    }

    public function testEmitsPaymentMethodTechnicalNameVerbatim(): void
    {
        // Plugin/app methods are emitted as-is; the label config (allowed_values) bounds cardinality, not the subscriber.
        $order = $this->createOrder(lineItems: 1, paymentTechnicalName: 'swag_paypal_apple_pay');

        $this->createSubscriber()->emitOrderPlacedMetrics($this->createEvent($order));

        static::assertSame('swag_paypal_apple_pay', $this->getMetric('order.placed.count')->labels['payment_method']);
    }

    public function testResolvesSalesChannelTypeLabel(): void
    {
        $order = $this->createOrder(lineItems: 1, paymentTechnicalName: 'payment_prepayment');

        $this->createSubscriber()->emitOrderPlacedMetrics($this->createEvent($order, Defaults::SALES_CHANNEL_TYPE_API));

        static::assertSame('api', $this->getMetric('order.placed.count')->labels['sales_channel_type']);
    }

    public function testPaymentMethodIsNoneWhenNoTransaction(): void
    {
        // `none` (not `other`) so orders with no attributable payment method stay distinct from the
        // real-but-uncurated long tail that the allowed_values REPLACE policy folds into `other`.
        $order = $this->createOrder(lineItems: 1, paymentTechnicalName: null);

        $this->createSubscriber()->emitOrderPlacedMetrics($this->createEvent($order));

        static::assertSame('none', $this->getMetric('order.placed.count')->labels['payment_method']);
    }

    public function testUsesLatestTransactionForPaymentMethod(): void
    {
        $order = $this->createOrder(lineItems: 1, paymentTechnicalName: null);
        $order->setTransactions(new OrderTransactionCollection([
            $this->transaction('first', 'payment_invoicepayment'),
            $this->transaction('second', 'swag_paypal_apple_pay'),
        ]));

        $this->createSubscriber()->emitOrderPlacedMetrics($this->createEvent($order));

        static::assertSame('swag_paypal_apple_pay', $this->getMetric('order.placed.count')->labels['payment_method']);
    }

    public function testLineItemsCountIsZeroWhenLineItemsNotLoaded(): void
    {
        $order = new OrderEntity();
        $order->setId($this->ids->get('order'));

        $this->createSubscriber()->emitOrderPlacedMetrics($this->createEvent($order));

        static::assertSame(0, $this->getMetric('order.line_items.count')->value);
    }

    private function getMetric(string $name): ConfiguredMetric
    {
        foreach ($this->emitted as $metric) {
            if ($metric->name === $name) {
                return $metric;
            }
        }

        static::fail(\sprintf('Metric "%s" was not emitted', $name));
    }

    private function createSubscriber(): OrderMetricsSubscriber
    {
        $meter = static::createStub(Meter::class);
        $meter->method('emit')->willReturnCallback(function (ConfiguredMetric $metric): void {
            $this->emitted[] = $metric;
        });

        return new OrderMetricsSubscriber($meter, new SalesChannelTypeResolver());
    }

    private function createOrder(int $lineItems, ?string $paymentTechnicalName): OrderEntity
    {
        $order = new OrderEntity();
        $order->setId($this->ids->get('order'));

        $collection = new OrderLineItemCollection();
        for ($i = 0; $i < $lineItems; ++$i) {
            $lineItem = new OrderLineItemEntity();
            $lineItem->setId($this->ids->get('line-item-' . $i));
            $collection->add($lineItem);
        }
        $order->setLineItems($collection);

        if ($paymentTechnicalName !== null) {
            $order->setTransactions(new OrderTransactionCollection([
                $this->transaction('transaction', $paymentTechnicalName),
            ]));
        }

        return $order;
    }

    private function transaction(string $key, string $technicalName): OrderTransactionEntity
    {
        $paymentMethod = new PaymentMethodEntity();
        $paymentMethod->setId($this->ids->get('payment-method-' . $key));
        $paymentMethod->setTechnicalName($technicalName);

        $transaction = new OrderTransactionEntity();
        $transaction->setId($this->ids->get($key));
        $transaction->setPaymentMethod($paymentMethod);

        return $transaction;
    }

    private function createEvent(OrderEntity $order, string $typeId = Defaults::SALES_CHANNEL_TYPE_STOREFRONT): CheckoutOrderPlacedEvent
    {
        $salesChannel = new SalesChannelEntity();
        $salesChannel->setTypeId($typeId);

        $context = static::createStub(SalesChannelContext::class);
        $context->method('getSalesChannel')->willReturn($salesChannel);

        return new CheckoutOrderPlacedEvent($context, $order);
    }
}
