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
use Shopware\Core\Framework\Telemetry\Metrics\Meter;
use Shopware\Core\Framework\Telemetry\Metrics\Metric\ConfiguredMetric;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Core\System\SalesChannel\Telemetry\SalesChannelTypeResolver;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(CartMetricsInstrumentor::class)]
class CartMetricsInstrumentorTest extends TestCase
{
    /**
     * @var list<ConfiguredMetric>
     */
    private array $emitted = [];

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

        static::assertCount(2, $this->emitted);

        $duration = $this->getMetric('cart.calculation.duration');
        static::assertIsFloat($duration->value);
        static::assertGreaterThanOrEqual(0.0, $duration->value);
        static::assertSame(['sales_channel_type' => 'storefront', 'has_promotions' => 'no'], $duration->labels);

        $lineItems = $this->getMetric('cart.line_items.count');
        static::assertSame(2, $lineItems->value);
        static::assertSame(['sales_channel_type' => 'storefront'], $lineItems->labels);
    }

    public function testHasPromotionsIsYesWhenPromotionLineItemPresent(): void
    {
        $cart = new Cart('token');
        $cart->add(new LineItem('a', 'product'));
        $cart->add(new LineItem('p', PromotionProcessor::LINE_ITEM_TYPE));

        $this->createInstrumentor()->measure($this->createContext(), fn (): Cart => $cart);

        static::assertSame('yes', $this->getMetric('cart.calculation.duration')->labels['has_promotions']);
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
        static::assertSame(1, $this->getMetric('cart.line_items.count')->value);
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

        $errors = array_values(array_filter(
            $this->emitted,
            static fn (ConfiguredMetric $m): bool => $m->name === 'cart.errors.count'
        ));

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

        $names = array_map(static fn (ConfiguredMetric $m): string => $m->name, $this->emitted);
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

        $errors = array_filter(
            $this->emitted,
            static fn (ConfiguredMetric $m): bool => $m->name === 'cart.errors.count'
        );

        // per calculation, like duration/line_items — no dedup
        static::assertCount(2, $errors);
    }

    public function testResolvesSalesChannelTypeLabel(): void
    {
        $cart = new Cart('token');

        $this->createInstrumentor()->measure($this->createContext(Defaults::SALES_CHANNEL_TYPE_API), fn (): Cart => $cart);

        static::assertSame('api', $this->getMetric('cart.calculation.duration')->labels['sales_channel_type']);
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

    private function createInstrumentor(): CartMetricsInstrumentor
    {
        $meter = static::createStub(Meter::class);
        $meter->method('emit')->willReturnCallback(function (ConfiguredMetric $metric): void {
            $this->emitted[] = $metric;
        });

        return new CartMetricsInstrumentor($meter, new SalesChannelTypeResolver());
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
