<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\Garan;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartBehavior;
use Shopware\Core\Checkout\Cart\LineItem\CartDataCollection;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\CheckoutPermissions;
use Shopware\Core\Content\Product\Garan\GaranLabelCartCollector;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Generator;
use Shopware\Tests\Unit\Core\Content\Product\Garan\_fixtures\GaranLabelFixtures;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(GaranLabelCartCollector::class)]
class GaranLabelCartCollectorTest extends TestCase
{
    private const STORED = ['guaranteeMonths' => 42, 'brand' => 'ACME', 'modelIdentifier' => 'ACME-36'];

    /**
     * @return \Generator<string, array{ProductEntity, array<string, int|string>|null}>
     */
    public static function productProvider(): \Generator
    {
        yield 'a product with a label stores its values' => [GaranLabelFixtures::createProduct('product-id'), GaranLabelFixtures::PRODUCT_LABEL];

        $unconfirmed = GaranLabelFixtures::createProduct('product-id');
        $unconfirmed->setGuaranteeConfirmed(false);

        yield 'a product without a label stores null, so the storefront does not look it up' => [$unconfirmed, null];
    }

    /**
     * @param array<string, int|string>|null $expected
     */
    #[DataProvider('productProvider')]
    public function testReplacesTheLabelWithTheOneOfTheLoadedProduct(ProductEntity $product, ?array $expected): void
    {
        $lineItem = self::createLineItem(['garanLabel' => self::STORED]);

        self::collect($lineItem, [$product], new CartBehavior());

        static::assertSame($expected, $lineItem->getPayload()['garanLabel']);
    }

    public function testKeepsTheStoredLabelWhenTheProductWasNotLoaded(): void
    {
        $lineItem = self::createLineItem(['garanLabel' => self::STORED]);

        self::collect($lineItem, [], new CartBehavior());

        static::assertSame(self::STORED, $lineItem->getPayload()['garanLabel']);
    }

    public function testAnEditedOrderKeepsTheLabelItWasPlacedWith(): void
    {
        $lineItem = self::createLineItem(['garanLabel' => self::STORED]);

        self::collect(
            $lineItem,
            [GaranLabelFixtures::createProduct('product-id')],
            new CartBehavior([CheckoutPermissions::SKIP_PRODUCT_RECALCULATION => true]),
        );

        static::assertSame(self::STORED, $lineItem->getPayload()['garanLabel']);
    }

    public function testStoresTheLabelOfProductsInsideAContainer(): void
    {
        $child = self::createLineItem([]);
        $container = new LineItem('container', LineItem::CONTAINER_LINE_ITEM);
        $container->addChild($child);

        self::collect($container, [GaranLabelFixtures::createProduct('product-id')], new CartBehavior());

        static::assertSame(GaranLabelFixtures::PRODUCT_LABEL, $child->getPayload()['garanLabel']);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private static function createLineItem(array $payload): LineItem
    {
        $lineItem = new LineItem('line-item-id', LineItem::PRODUCT_LINE_ITEM_TYPE, 'product-id');
        $lineItem->setPayload($payload);

        return $lineItem;
    }

    /**
     * @param list<ProductEntity> $loadedProducts
     */
    private static function collect(LineItem $lineItem, array $loadedProducts, CartBehavior $behavior): void
    {
        $cart = new Cart('test');
        $cart->add($lineItem);

        // the ProductCartProcessor adds the products it loaded to the cart data
        $data = new CartDataCollection();
        foreach ($loadedProducts as $product) {
            $data->set('product-' . $product->getId(), $product);
        }

        $collector = new GaranLabelCartCollector(GaranLabelFixtures::createResolver());
        $collector->collect($data, $cart, Generator::generateSalesChannelContext(), $behavior);
    }
}
