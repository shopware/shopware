<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\Garan;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\Order\CartConvertedEvent;
use Shopware\Core\Checkout\Cart\Order\OrderConversionContext;
use Shopware\Core\Content\Product\Garan\GaranLabelOrderSubscriber;
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
    private const STORED = ['guaranteeMonths' => 42, 'brand' => 'ACME', 'modelIdentifier' => 'ACME-36'];

    public function testSubscribesToCartConversion(): void
    {
        static::assertSame(
            [CartConvertedEvent::class => 'storeLabels'],
            GaranLabelOrderSubscriber::getSubscribedEvents()
        );
    }

    /**
     * @return \Generator<string, array{array<string, mixed>, array<string, int|string>|null}>
     */
    public static function labelProvider(): \Generator
    {
        yield 'a line item without label, from an old cart or added to an edited order, gets the current label' => [[], GaranLabelFixtures::PRODUCT_LABEL];
        yield 'a line item keeps the label its cart showed or the edited order was placed with' => [['garanLabel' => self::STORED], self::STORED];
        yield 'a line item keeps having no label' => [['garanLabel' => null], null];
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, int|string>|null $expected
     */
    #[DataProvider('labelProvider')]
    public function testStoresTheLabelInTheLineItemPayload(array $payload, ?array $expected): void
    {
        $event = self::createEvent([['productId' => 'product-id', 'payload' => $payload]]);

        $this->createSubscriber([new ProductCollection([GaranLabelFixtures::createProduct('product-id')])])->storeLabels($event);

        static::assertSame($expected, $event->getConvertedCart()['lineItems'][0]['payload']['garanLabel']);
    }

    public function testSearchesEachProductOnceWithInheritanceAndSkipsLineItemsWithoutProduct(): void
    {
        $event = self::createEvent([
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
        static::assertSame(GaranLabelFixtures::PRODUCT_LABEL, $lineItems[0]['payload']['garanLabel']);
        static::assertArrayNotHasKey('garanLabel', $lineItems[1]['payload']);
        static::assertSame(GaranLabelFixtures::PRODUCT_LABEL, $lineItems[2]['payload']['garanLabel']);
    }

    public function testLeavesTheLabelOutForDeletedProducts(): void
    {
        $event = self::createEvent([['productId' => 'deleted-product-id', 'payload' => []]]);

        $this->createSubscriber([new ProductCollection()])->storeLabels($event);

        static::assertArrayNotHasKey('garanLabel', $event->getConvertedCart()['lineItems'][0]['payload']);
    }

    public function testDoesNotSearchWhenNoLineItemNeedsTheCurrentLabel(): void
    {
        $event = self::createEvent([
            ['productId' => null, 'payload' => []],
            ['productId' => 'product-id', 'payload' => ['garanLabel' => self::STORED]],
        ]);

        // the repository throws on a search it has no result for
        $this->createSubscriber([])->storeLabels($event);

        static::assertSame(self::STORED, $event->getConvertedCart()['lineItems'][1]['payload']['garanLabel']);
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
    private static function createEvent(array $lineItems): CartConvertedEvent
    {
        return new CartConvertedEvent(
            new Cart('test'),
            ['lineItems' => $lineItems],
            Generator::generateSalesChannelContext(),
            new OrderConversionContext(),
        );
    }
}
