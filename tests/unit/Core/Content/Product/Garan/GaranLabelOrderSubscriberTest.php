<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\Garan;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\Order\CartConvertedEvent;
use Shopware\Core\Checkout\Cart\Order\OrderConversionContext;
use Shopware\Core\Content\Product\Garan\GaranLabelOrderSubscriber;
use Shopware\Core\Content\Product\Garan\GaranLabelResolver;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Generator;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Shopware\Tests\Unit\Core\Content\Product\Garan\_fixtures\GaranLabelFixtures;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(GaranLabelOrderSubscriber::class)]
class GaranLabelOrderSubscriberTest extends TestCase
{
    public function testSubscribesToCartConversion(): void
    {
        static::assertSame(
            [
                CartConvertedEvent::class => 'storeLabels',
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
        $productRepository = StaticEntityRepository::of(ProductCollection::class, $searchResults, new ProductDefinition());

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
}
