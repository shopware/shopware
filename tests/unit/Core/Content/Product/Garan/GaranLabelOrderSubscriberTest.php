<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\Garan;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\Order\CartConvertedEvent;
use Shopware\Core\Checkout\Cart\Order\OrderConversionContext;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemDefinition;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Checkout\Order\OrderEvents;
use Shopware\Core\Content\Product\Garan\GaranLabelOrderSubscriber;
use Shopware\Core\Content\Product\Garan\GaranLabelResolver;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\Api\Context\SalesChannelApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Annotation\DisabledFeatures;
use Shopware\Core\Test\Generator;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Shopware\Core\Test\TestDefaults;
use Shopware\Tests\Unit\Core\Content\Product\Garan\_fixtures\GaranLabelFixtures;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(GaranLabelOrderSubscriber::class)]
class GaranLabelOrderSubscriberTest extends TestCase
{
    public function testSubscribesToCartConversionAndOrderLineItemReads(): void
    {
        static::assertSame(
            [
                CartConvertedEvent::class => 'storeLabels',
                OrderEvents::ORDER_LINE_ITEM_LOADED_EVENT => 'fillMissingLabels',
            ],
            GaranLabelOrderSubscriber::getSubscribedEvents()
        );
    }

    /**
     * @return \Generator<string, array{array<string, mixed>, ProductCollection, array<string, int|string>|null}>
     */
    public static function labelProvider(): \Generator
    {
        $products = new ProductCollection([GaranLabelFixtures::createProduct('product-id')]);

        yield 'a line item without label, from an old cart or order, or added to an edited order, gets the current label' => [[], $products, GaranLabelFixtures::PRODUCT_LABEL];
        yield 'a line item keeps the label its cart showed or the order was placed with' => [[GaranLabelResolver::PAYLOAD_KEY => GaranLabelFixtures::STORED_LABEL], $products, GaranLabelFixtures::STORED_LABEL];
        yield 'a line item keeps having no label' => [[GaranLabelResolver::PAYLOAD_KEY => null], $products, null];
        yield 'a line item of a deleted product gets no label' => [[], new ProductCollection(), null];
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, int|string>|null $expected
     */
    #[DataProvider('labelProvider')]
    public function testStoresTheLabelWhenTheCartIsConverted(array $payload, ProductCollection $products, ?array $expected): void
    {
        $event = self::createConvertedEvent([['productId' => 'product-id', 'payload' => $payload]]);

        $this->createSubscriber([$products])->storeLabels($event);

        static::assertSame($expected, $event->getConvertedCart()['lineItems'][0]['payload'][GaranLabelResolver::PAYLOAD_KEY]);
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, int|string>|null $expected
     */
    #[DataProvider('labelProvider')]
    #[DisabledFeatures(['v6.8.0.0'])]
    public function testFillsTheLabelWhenASalesChannelReadsTheOrder(array $payload, ProductCollection $products, ?array $expected): void
    {
        $lineItem = self::createOrderLineItem($payload);

        $this->createSubscriber([$products])->fillMissingLabels(self::createLoadedEvent($lineItem, self::createSalesChannelApiContext()));

        $payload = $lineItem->getPayload() ?? [];
        static::assertArrayHasKey(GaranLabelResolver::PAYLOAD_KEY, $payload, 'the storefront would look up the product');
        static::assertSame($expected, $payload[GaranLabelResolver::PAYLOAD_KEY]);
    }

    /**
     * @return \Generator<string, array{Context, list<string>}>
     */
    public static function storedLabelOnlyProvider(): \Generator
    {
        yield 'the Admin API and the system read what is stored' => [Context::createDefaultContext(), []];
        yield 'from 6.8 on, sales channels read what is stored' => [self::createSalesChannelApiContext(), ['v6.8.0.0']];
    }

    /**
     * @param list<string> $activeFeatures
     */
    #[DataProvider('storedLabelOnlyProvider')]
    public function testLeavesOrderLineItemsWithoutLabelAlone(Context $context, array $activeFeatures): void
    {
        $lineItem = self::createOrderLineItem([]);

        // the repository throws on a search it has no result for
        Feature::fake($activeFeatures, fn () => $this->createSubscriber([])->fillMissingLabels(self::createLoadedEvent($lineItem, $context)));

        static::assertArrayNotHasKey(GaranLabelResolver::PAYLOAD_KEY, $lineItem->getPayload() ?? []);
    }

    public function testSearchesEachProductOnceWithInheritanceAndSkipsLineItemsWithoutProduct(): void
    {
        $event = self::createConvertedEvent([
            ['productId' => 'product-id', 'payload' => []],
            ['productId' => null, 'payload' => []],
            ['productId' => 'product-id', 'payload' => []],
        ]);

        $this->createSubscriber([
            static function (Criteria $criteria, Context $context): ProductCollection {
                static::assertSame(['product-id'], $criteria->getIds());
                static::assertTrue($criteria->hasAssociation('manufacturer'));
                static::assertTrue($context->considerInheritance(), 'variants inherit the guarantee and manufacturer');

                return new ProductCollection([GaranLabelFixtures::createProduct('product-id')]);
            },
        ])->storeLabels($event);

        $lineItems = $event->getConvertedCart()['lineItems'];
        static::assertSame(GaranLabelFixtures::PRODUCT_LABEL, $lineItems[0]['payload'][GaranLabelResolver::PAYLOAD_KEY]);
        static::assertArrayNotHasKey(GaranLabelResolver::PAYLOAD_KEY, $lineItems[1]['payload']);
        static::assertSame(GaranLabelFixtures::PRODUCT_LABEL, $lineItems[2]['payload'][GaranLabelResolver::PAYLOAD_KEY]);
    }

    public function testDoesNotSearchWhenNoLineItemNeedsTheCurrentLabel(): void
    {
        $event = self::createConvertedEvent([
            ['productId' => null, 'payload' => []],
            ['productId' => 'product-id', 'payload' => [GaranLabelResolver::PAYLOAD_KEY => GaranLabelFixtures::STORED_LABEL]],
        ]);

        // the repository throws on a search it has no result for
        $this->createSubscriber([])->storeLabels($event);

        static::assertSame(GaranLabelFixtures::STORED_LABEL, $event->getConvertedCart()['lineItems'][1]['payload'][GaranLabelResolver::PAYLOAD_KEY]);
    }

    /**
     * @param list<mixed> $searchResults
     */
    private function createSubscriber(array $searchResults): GaranLabelOrderSubscriber
    {
        /** @var StaticEntityRepository<ProductCollection> $productRepository */
        $productRepository = new StaticEntityRepository($searchResults, new ProductDefinition());

        return new GaranLabelOrderSubscriber($productRepository, GaranLabelFixtures::createResolver());
    }

    /**
     * @param list<array{productId: string|null, payload: array<string, mixed>}> $lineItems
     */
    private static function createConvertedEvent(array $lineItems): CartConvertedEvent
    {
        return new CartConvertedEvent(
            new Cart('test'),
            ['lineItems' => $lineItems],
            Generator::generateSalesChannelContext(),
            new OrderConversionContext(),
        );
    }

    /**
     * @param array<string, mixed> $payload
     */
    private static function createOrderLineItem(array $payload): OrderLineItemEntity
    {
        $lineItem = new OrderLineItemEntity();
        $lineItem->setId('line-item-id');
        $lineItem->setProductId('product-id');
        $lineItem->setPayload($payload);

        return $lineItem;
    }

    private static function createSalesChannelApiContext(): Context
    {
        return new Context(new SalesChannelApiSource(TestDefaults::SALES_CHANNEL));
    }

    /**
     * @return EntityLoadedEvent<OrderLineItemEntity>
     */
    private static function createLoadedEvent(OrderLineItemEntity $lineItem, Context $context): EntityLoadedEvent
    {
        return new EntityLoadedEvent(new OrderLineItemDefinition(), [$lineItem], $context);
    }
}
