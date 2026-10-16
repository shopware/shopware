<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart\Telemetry;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\Error\Error;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Telemetry\CartMetricsInstrumentor;
use Shopware\Core\Checkout\Promotion\Cart\PromotionProcessor;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Telemetry\Telemetry;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Core\System\SalesChannel\Telemetry\SalesChannelTypeResolver;
use Shopware\Core\Test\Stub\Telemetry\CollectingMeter;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(CartMetricsInstrumentor::class)]
class CartMetricsInstrumentorTest extends TestCase
{
    private CollectingMeter $meter;

    public function testMeasureReturnsCartFromCallback(): void
    {
        $cart = new Cart('token');

        $result = $this->createInstrumentor()->measure($this->createContext(), fn (): Cart => $cart);

        static::assertSame($cart, $result);
    }

    public function testEmitsDurationLineItemsAndNoErrorsForCleanCart(): void
    {
        $cart = new Cart('token');
        $cart->add(new LineItem('a', 'product'));
        $cart->add(new LineItem('b', 'product'));

        $this->createInstrumentor()->measure($this->createContext(), fn (): Cart => $cart);

        static::assertCount(2, $this->meter->getMetrics());

        $duration = $this->meter->getMetric('cart.calculation.duration');
        static::assertIsFloat($duration->value);
        static::assertGreaterThanOrEqual(0.0, $duration->value);
        static::assertSame(['sales_channel_type' => 'storefront', 'has_promotions' => 'no', 'result' => 'success'], $this->meter->getLabels('cart.calculation.duration'));

        $lineItems = $this->meter->getMetric('cart.line_items.count');
        static::assertSame(2, $lineItems->value);
        static::assertSame(['sales_channel_type' => 'storefront'], $lineItems->labels);
    }

    public function testHasPromotionsIsYesWhenPromotionLineItemPresent(): void
    {
        $cart = new Cart('token');
        $cart->add(new LineItem('a', 'product'));
        $cart->add(new LineItem('p', PromotionProcessor::LINE_ITEM_TYPE));

        $this->createInstrumentor()->measure($this->createContext(), fn (): Cart => $cart);

        $labels = $this->meter->getLabels('cart.calculation.duration');
        static::assertSame('yes', $labels['has_promotions']);
    }

    public function testLineItemCountCountsTopLevelRowsNotNestedChildren(): void
    {
        $parent = new LineItem('parent', 'product');
        $parent->addChild(new LineItem('child-a', 'product'));
        $parent->addChild(new LineItem('child-b', 'product'));

        $cart = new Cart('token');
        $cart->add($parent);

        $this->createInstrumentor()->measure($this->createContext(), fn (): Cart => $cart);

        // one top-level row; the two nested children (bundle/container structure) are not counted
        static::assertSame(1, $this->meter->getMetric('cart.line_items.count')->value);
    }

    public function testEmitsAggregateErrorCountExcludingNotices(): void
    {
        $cart = new Cart('token');
        $cart->addErrors(
            $this->error('e1', Error::LEVEL_ERROR, 'product-out-of-stock'),
            $this->error('e2', Error::LEVEL_WARNING, 'payment-method-blocked'),
            // informational cart notices ("discount applied", "method switched") are not errors
            $this->error('n1', Error::LEVEL_NOTICE, 'promotion-discount-added'),
            $this->error('n2', Error::LEVEL_NOTICE, 'shipping-method-changed'),
            // any level below warning
            $this->error('g1', Error::LEVEL_WARNING - 1, 'below-warning'),
        );

        $this->createInstrumentor()->measure($this->createContext(), fn (): Cart => $cart);

        $errors = $this->meter->getMetrics('cart.errors.count');

        // one aggregate emit per calculation, no labels; value counts the two warning/error entries only
        static::assertCount(1, $errors);
        static::assertSame(2, $errors[0]->value);
        static::assertSame([], $errors[0]->labels);
    }

    public function testDoesNotEmitWhenNoWarningOrErrorErrors(): void
    {
        $cart = new Cart('token');
        $cart->addErrors($this->error('n1', Error::LEVEL_NOTICE, 'promotion-discount-added'));

        $this->createInstrumentor()->measure($this->createContext(), fn (): Cart => $cart);

        $names = $this->meter->getMetricNames();
        static::assertNotContains('cart.errors.count', $names);
    }

    public function testErrorCountIsEmittedPerCalculation(): void
    {
        $error = $this->error('same-id', Error::LEVEL_ERROR, 'product-out-of-stock');
        $instrumentor = $this->createInstrumentor();

        $first = new Cart('token');
        $first->addErrors($error);
        $instrumentor->measure($this->createContext(), fn (): Cart => $first);

        $second = new Cart('token');
        $second->addErrors($error);
        $instrumentor->measure($this->createContext(), fn (): Cart => $second);

        $errors = $this->meter->getMetrics('cart.errors.count');

        // per calculation, like duration/line_items — no dedup
        static::assertCount(2, $errors);
    }

    public function testResolvesSalesChannelTypeLabel(): void
    {
        $cart = new Cart('token');

        $this->createInstrumentor()->measure($this->createContext(Defaults::SALES_CHANNEL_TYPE_API), fn (): Cart => $cart);

        $labels = $this->meter->getLabels('cart.calculation.duration');
        static::assertSame('api', $labels['sales_channel_type']);
    }

    public function testFailingCalculationIsRethrownAndDurationRecordedAsFailed(): void
    {
        $thrown = null;

        try {
            $this->createInstrumentor()->measure($this->createContext(), function (): Cart {
                throw new \RuntimeException('cart calculation failed');
            });
        } catch (\RuntimeException $e) {
            $thrown = $e;
        }

        static::assertNotNull($thrown, 'the original exception must propagate');
        static::assertSame('cart calculation failed', $thrown->getMessage());

        // the duration is still recorded; the cart is unknown, so has_promotions falls back to 'no'
        $labels = $this->meter->getLabels('cart.calculation.duration');
        static::assertSame('failed', $labels['result']);
        static::assertSame('no', $labels['has_promotions']);

        // follow-up metrics need the calculated cart and are skipped
        $names = $this->meter->getMetricNames();
        static::assertNotContains('cart.line_items.count', $names);
        static::assertNotContains('cart.errors.count', $names);
    }

    private function createInstrumentor(): CartMetricsInstrumentor
    {
        $this->meter = new CollectingMeter();

        return new CartMetricsInstrumentor(new Telemetry($this->meter, 'test'), new SalesChannelTypeResolver());
    }

    private function createContext(string $typeId = Defaults::SALES_CHANNEL_TYPE_STOREFRONT): SalesChannelContext
    {
        $salesChannel = new SalesChannelEntity();
        $salesChannel->setTypeId($typeId);

        $context = static::createStub(SalesChannelContext::class);
        $context->method('getSalesChannel')->willReturn($salesChannel);

        return $context;
    }

    private function error(string $id, int $level, string $messageKey): Error
    {
        return new class($id, $level, $messageKey) extends Error {
            public function __construct(
                private readonly string $id,
                private readonly int $level,
                private readonly string $messageKey,
            ) {
                parent::__construct($messageKey);
            }

            public function getId(): string
            {
                return $this->id;
            }

            public function getMessageKey(): string
            {
                return $this->messageKey;
            }

            public function getLevel(): int
            {
                return $this->level;
            }

            public function blockOrder(): bool
            {
                return false;
            }

            public function getParameters(): array
            {
                return [];
            }
        };
    }
}
