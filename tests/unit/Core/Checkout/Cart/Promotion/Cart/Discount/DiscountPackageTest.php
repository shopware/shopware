<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart\Promotion\Cart\Discount;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Exception\LineItemNotFoundException;
use Shopware\Core\Checkout\Cart\LineItem\Group\LineItemQuantity;
use Shopware\Core\Checkout\Cart\LineItem\Group\LineItemQuantityCollection;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemFlatCollection;
use Shopware\Core\Checkout\Promotion\Cart\Discount\DiscountPackage;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Integration\Traits\Promotion\PromotionLineItemTestFixtureBehaviour;

/**
 * @internal
 */
#[CoversClass(DiscountPackage::class)]
#[Package('checkout')]
class DiscountPackageTest extends TestCase
{
    use PromotionLineItemTestFixtureBehaviour;

    /**
     * This test verifies that we have an empty and valid
     * list for new objects.
     */
    #[Group('promotions')]
    public function testMetaDataItemsEmptyOnNewObject(): void
    {
        $package = new DiscountPackage(new LineItemQuantityCollection());

        static::assertCount(0, $package->getMetaData());
    }

    /**
     * This test verifies that we correctly assign the
     * provided list of our line item quantity items and
     * return it in the getter.
     */
    #[Group('promotions')]
    public function testMetaDataItemsAreCorrectlyAdded(): void
    {
        $items = new LineItemQuantityCollection();
        $items->add(new LineItemQuantity('ABC', 2));

        $package = new DiscountPackage(new LineItemQuantityCollection($items));

        static::assertCount(1, $package->getMetaData());
    }

    /**
     * This test verifies that we have an empty and valid
     * list for new objects.
     */
    #[Group('promotions')]
    public function testCartItemsEmptyOnNewObject(): void
    {
        $package = new DiscountPackage(new LineItemQuantityCollection());

        static::assertCount(0, $package->getCartItems());
    }

    /**
     * This test verifies that we correctly assign the
     * provided list of our cart items and return it in the getter.
     */
    #[Group('promotions')]
    public function testCartItemsAreCorrectlyAdded(): void
    {
        $cartItems = new LineItemFlatCollection();
        $product = $this->createProductItem(29, 19);
        $cartItems->add($product);

        $package = new DiscountPackage(new LineItemQuantityCollection());
        $package->setCartItems($cartItems);

        static::assertCount(1, $package->getCartItems());
    }

    /**
     * This test verifies that we don't get an exception
     * when requesting the price without any items.
     * We have to get 0,00 in this case.
     */
    #[Group('promotions')]
    public function testTotalPriceOnEmptyItems(): void
    {
        $package = new DiscountPackage(new LineItemQuantityCollection());

        static::assertSame(0.0, $package->getTotalPrice());
    }

    /**
     * This test verifies that we don't get an exception
     * when requesting the price without any assigned cart items.
     * So we have our metadata with the quantity data, but no real
     * cart items in there.
     * We have to get 0,00 in this case.
     */
    #[Group('promotions')]
    public function testTotalPriceWithoutAssignedCartItems(): void
    {
        $items = new LineItemQuantityCollection();
        $items->add(new LineItemQuantity('ABC', 2));

        $package = new DiscountPackage($items);

        static::assertSame(0.0, $package->getTotalPrice());
    }

    /**
     * This test verifies that we get the correct total
     * price from the list of assigned cart items in our package.
     */
    #[Group('promotions')]
    public function testTotalPriceWithItems(): void
    {
        $items = new LineItemQuantityCollection();
        $items->add(new LineItemQuantity('ABC', 2));

        $cartItems = new LineItemFlatCollection();
        $product = $this->createProductItem(29, 19);
        $cartItems->add($product);

        $package = new DiscountPackage($items);
        $package->setCartItems($cartItems);

        static::assertSame(29.0, $package->getTotalPrice());
    }

    /**
     * This test verifies that we have an empty and valid
     * list for new objects.
     */
    #[Group('promotions')]
    public function testAffectedPricesOnNewObject(): void
    {
        $package = new DiscountPackage(new LineItemQuantityCollection());

        static::assertCount(0, $package->getAffectedPrices());
    }

    /**
     * This test verifies that our affected price function
     * does correctly collect the price collections from our cart items.
     */
    #[Group('promotions')]
    public function testAffectedPricesWithCartItems(): void
    {
        $cartItems = new LineItemFlatCollection();

        $product = $this->createProductItem(29, 19);
        $cartItems->add($product);

        $product = $this->createProductItem(14, 19);
        $cartItems->add($product);

        $package = new DiscountPackage(new LineItemQuantityCollection());
        $package->setCartItems($cartItems);

        static::assertCount(2, $package->getAffectedPrices());
    }

    /**
     * This test verifies that our affected price function
     * does correctly collect the price collections from our cart items.
     */
    #[Group('promotions')]
    public function testGetItem(): void
    {
        $itemId1 = Uuid::randomHex();
        $itemId2 = Uuid::randomHex();
        $itemId3 = Uuid::randomHex();
        $itemId4 = Uuid::randomHex();

        $cartItems = new LineItemFlatCollection([
            $lineItem1 = new LineItem($itemId1, 'main-product'),
            new LineItem($itemId2, 'product'),
            $lineItem3 = new LineItem($itemId3, 'product'),
            new LineItem($itemId4, 'product'),
            new LineItem($itemId1, 'other-product'),
            new LineItem($itemId1, 'sub-product'),
            new LineItem($itemId2, 'product'),
        ]);

        $package = new DiscountPackage(new LineItemQuantityCollection());
        $package->setCartItems($cartItems);

        static::assertSame($package->getCartItem($itemId1), $lineItem1);
        static::assertSame($package->getCartItem($itemId3), $lineItem3);

        // set new collection, reset hash
        $package->setCartItems(new LineItemFlatCollection([
            $newLineItem1 = new LineItem($itemId1, 'new-product'),
            new LineItem($itemId2, 'product'),
        ]));

        static::assertNotSame($package->getCartItem($itemId1), $lineItem1);
        static::assertSame($package->getCartItem($itemId1), $newLineItem1);

        $this->expectException(LineItemNotFoundException::class);
        $package->getCartItem(Uuid::randomHex());
    }
}
