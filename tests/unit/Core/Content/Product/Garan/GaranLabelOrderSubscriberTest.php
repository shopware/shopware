<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\Garan;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartBehavior;
use Shopware\Core\Checkout\Cart\Order\CartConvertedEvent;
use Shopware\Core\Checkout\Cart\Order\OrderConversionContext;
use Shopware\Core\Checkout\CheckoutPermissions;
use Shopware\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerEntity;
use Shopware\Core\Content\Product\Garan\GaranLabelDurationFormatter;
use Shopware\Core\Content\Product\Garan\GaranLabelOrderSubscriber;
use Shopware\Core\Content\Product\Garan\GaranLabelRenderer;
use Shopware\Core\Content\Product\Garan\GaranLabelResolver;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Generator;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Twig\Environment;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(GaranLabelOrderSubscriber::class)]
class GaranLabelOrderSubscriberTest extends TestCase
{
    private const PLACED_WITH = ['guaranteeMonths' => 42, 'brand' => 'ACME', 'modelIdentifier' => 'ACME-36'];

    private const CURRENT = ['guaranteeMonths' => 36, 'brand' => 'ACME', 'modelIdentifier' => 'ACME-36'];

    public function testSubscribesToCartConversion(): void
    {
        static::assertSame(
            [CartConvertedEvent::class => 'storeLabels'],
            GaranLabelOrderSubscriber::getSubscribedEvents()
        );
    }

    /**
     * @return \Generator<string, array{array<string, mixed>, bool, array<string, int|string>|null}>
     */
    public static function labelProvider(): \Generator
    {
        yield 'a new order stores the current label' => [[], false, self::CURRENT];
        yield 'a new order replaces a label stored earlier with the current one' => [['garanLabel' => self::PLACED_WITH], false, self::CURRENT];
        yield 'an edited order keeps the label it was placed with' => [['garanLabel' => self::PLACED_WITH], true, self::PLACED_WITH];
        yield 'an edited order keeps having no label' => [['garanLabel' => null], true, null];
        yield 'a product added to an edited order gets the current label' => [[], true, self::CURRENT];
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, int|string>|null $expected
     */
    #[DataProvider('labelProvider')]
    public function testStoresTheLabelInTheLineItemPayload(array $payload, bool $isExistingOrder, ?array $expected): void
    {
        $event = self::createEvent([['productId' => 'product-id', 'payload' => $payload]], $isExistingOrder);

        $this->createSubscriber([new ProductCollection([self::createProduct('product-id')])])->storeLabels($event);

        static::assertSame($expected, $event->getConvertedCart()['lineItems'][0]['payload']['garanLabel']);
    }

    public function testSearchesEachProductOnceWithInheritanceAndSkipsLineItemsWithoutProduct(): void
    {
        $event = self::createEvent([
            ['productId' => 'product-id', 'payload' => []],
            ['productId' => null, 'payload' => []],
            ['productId' => 'product-id', 'payload' => []],
        ], false);

        $this->createSubscriber([
            static function (Criteria $criteria, Context $context): ProductCollection {
                static::assertSame(['product-id'], $criteria->getIds());
                static::assertTrue($criteria->hasAssociation('manufacturer'));
                static::assertTrue($context->considerInheritance(), 'variants inherit the guarantee and manufacturer');

                return new ProductCollection([self::createProduct('product-id')]);
            },
        ])->storeLabels($event);

        $lineItems = $event->getConvertedCart()['lineItems'];
        static::assertSame(self::CURRENT, $lineItems[0]['payload']['garanLabel']);
        static::assertArrayNotHasKey('garanLabel', $lineItems[1]['payload']);
        static::assertSame(self::CURRENT, $lineItems[2]['payload']['garanLabel']);
    }

    public function testLeavesTheLabelOutForDeletedProducts(): void
    {
        $event = self::createEvent([['productId' => 'deleted-product-id', 'payload' => []]], false);

        $this->createSubscriber([new ProductCollection()])->storeLabels($event);

        static::assertArrayNotHasKey('garanLabel', $event->getConvertedCart()['lineItems'][0]['payload']);
    }

    public function testDoesNotSearchWhenNoLineItemNeedsTheCurrentLabel(): void
    {
        $event = self::createEvent([
            ['productId' => null, 'payload' => []],
            ['productId' => 'product-id', 'payload' => ['garanLabel' => self::PLACED_WITH]],
        ], true);

        // the repository throws on a search it has no result for
        $this->createSubscriber([])->storeLabels($event);

        static::assertSame(self::PLACED_WITH, $event->getConvertedCart()['lineItems'][1]['payload']['garanLabel']);
    }

    /**
     * @param list<mixed> $searchResults
     */
    private function createSubscriber(array $searchResults): GaranLabelOrderSubscriber
    {
        /** @var StaticEntityRepository<ProductCollection> $productRepository */
        $productRepository = new StaticEntityRepository($searchResults, new ProductDefinition());

        return new GaranLabelOrderSubscriber(
            $productRepository,
            new GaranLabelResolver(
                new GaranLabelDurationFormatter(),
                new GaranLabelRenderer(static::createStub(Environment::class)),
            ),
        );
    }

    /**
     * @param list<array{productId: string|null, payload: array<string, mixed>}> $lineItems
     */
    private static function createEvent(array $lineItems, bool $isExistingOrder): CartConvertedEvent
    {
        $cart = new Cart('test');
        $cart->setBehavior(new CartBehavior([CheckoutPermissions::SKIP_PRODUCT_RECALCULATION => $isExistingOrder]));

        return new CartConvertedEvent(
            $cart,
            ['lineItems' => $lineItems],
            Generator::generateSalesChannelContext(),
            new OrderConversionContext(),
        );
    }

    private static function createProduct(string $id): ProductEntity
    {
        $manufacturer = new ProductManufacturerEntity();
        $manufacturer->setId('manufacturer-id');
        $manufacturer->setTranslated(['name' => 'ACME']);

        $product = new ProductEntity();
        $product->setId($id);
        $product->setManufacturer($manufacturer);
        $product->setManufacturerNumber('ACME-36');
        $product->setGuaranteeMonths(36);
        $product->setGuaranteeConfirmed(true);

        return $product;
    }
}
