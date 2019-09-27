<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Cart\Promotion\Unit\Cart\Discount;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\LineItem\Group\LineItemQuantity;
use Shopware\Core\Checkout\Cart\LineItem\Group\LineItemQuantityCollection;
use Shopware\Core\Checkout\Cart\LineItem\LineItemFlatCollection;
use Shopware\Core\Checkout\Promotion\Cart\Discount\DiscountPackage;
use Shopware\Core\Checkout\Test\Cart\Promotion\Helpers\Traits\PromotionLineItemTestFixtureBehaviour;

class DiscountPackageTest extends TestCase
{
    use PromotionLineItemTestFixtureBehaviour;

    /**
     * This test verifies that we have an empty and valid
     * list for new objects.
     *
     * @test
     * @group promotions
     */
    public function testMetaDataItemsEmptyOnNewObject(): void
    {
        $package = new DiscountPackage(new LineItemQuantityCollection());

        static::assertEquals(0, $package->getMetaData()->count());
    }

    /**
     * This test verifies that we correctly assign the
     * provided list of our line item quantity items and
     * return it in the getter.
     *
     * @test
     * @group promotions
     */
    public function testMetaDataItemsAreCorrectlyAdded(): void
    {
        $items = new LineItemQuantityCollection();
        $items->add(new LineItemQuantity('ABC', 2));

        $package = new DiscountPackage(new LineItemQuantityCollection($items));

        static::assertEquals(1, $package->getMetaData()->count());
    }

    /**
     * This test verifies that we have an empty and valid
     * list for new objects.
     *
     * @test
     * @group promotions
     */
    public function testCartItemsEmptyOnNewObject(): void
    {
        $package = new DiscountPackage(new LineItemQuantityCollection());

        static::assertEquals(0, $package->getCartItems()->count());
    }

    /**
     * This test verifies that we correctly assign the
     * provided list of our cart items and return it in the getter.
     *
     * @test
     * @group promotions
     */
    public function testCartItemsAreCorrectlyAdded(): void
    {
        $cartItems = new LineItemFlatCollection();
        $product = $this->createProductItem(29, 19);
        $cartItems->add($product);

        $package = new DiscountPackage(new LineItemQuantityCollection());
        $package->setCartItems($cartItems);

        static::assertEquals(1, $package->getCartItems()->count());
    }

    /**
     * This test verifies that we dont get an exception
     * when requesting the price without any items.
     * We have to get 0,00 in this case.
     *
     * @test
     * @group promotions
     */
    public function testTotalPriceOnEmptyItems(): void
    {
        $package = new DiscountPackage(new LineItemQuantityCollection());

        static::assertEquals(0, $package->getTotalPrice());
    }

    /**
     * This test verifies that we dont get an exception
     * when requesting the price without any assigned cart items.
     * So we have our meta data with the quantity data, but no real
     * cart items in there.
     * We have to get 0,00 in this case.
     *
     * @test
     * @group promotions
     */
    public function testTotalPriceWithoutAssignedCartItems(): void
    {
        $items = new LineItemQuantityCollection();
        $items->add(new LineItemQuantity('ABC', 2));

        $package = new DiscountPackage($items);

        static::assertEquals(0, $package->getTotalPrice());
    }

    /**
     * This test verifies that we get the correct total
     * price from the list of assigned cart items in our package.
     *
     * @test
     * @group promotions
     */
    public function testTotalPriceWithItems(): void
    {
        $items = new LineItemQuantityCollection();
        $items->add(new LineItemQuantity('ABC', 2));

        $cartItems = new LineItemFlatCollection();
        $product = $this->createProductItem(29, 19);
        $cartItems->add($product);

        $package = new DiscountPackage($items);
        $package->setCartItems($cartItems);

        static::assertEquals(29, $package->getTotalPrice());
    }

    /**
     * This test verifies that we have an empty and valid
     * list for new objects.
     *
     * @test
     * @group promotions
     */
    public function testAffectedPricesOnNewObject(): void
    {
        $package = new DiscountPackage(new LineItemQuantityCollection());

        static::assertEquals(0, $package->getAffectedPrices()->count());
    }

    /**
     * This test verifies that our affected price function
     * does correctly collect the price collections from our cart items.
     *
     * @test
     * @group promotions
     */
    public function testAffectedPricesWithCartItems(): void
    {
        $cartItems = new LineItemFlatCollection();

        $product = $this->createProductItem(29, 19);
        $cartItems->add($product);

        $product = $this->createProductItem(14, 19);
        $cartItems->add($product);

        $package = new DiscountPackage(new LineItemQuantityCollection());
        $package->setCartItems($cartItems);

        static::assertEquals(2, $package->getAffectedPrices()->count());
    }
}
