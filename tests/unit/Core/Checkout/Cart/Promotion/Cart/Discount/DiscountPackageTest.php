<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart\Promotion\Cart\Discount;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\LineItem\Group\LineItemQuantity;
use Shopware\Core\Checkout\Cart\LineItem\Group\LineItemQuantityCollection;
use Shopware\Core\Checkout\Cart\LineItem\LineItemFlatCollection;
use Shopware\Core\Checkout\Promotion\Cart\Discount\DiscountPackage;
use Shopware\Tests\Integration\Core\Checkout\Cart\Promotion\Helpers\Traits\PromotionLineItemTestFixtureBehaviour;

/**
 * @internal
 */
#[CoversClass(DiscountPackage::class)]
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

        static::assertEquals(0, $package->getMetaData()->count());
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

        static::assertEquals(1, $package->getMetaData()->count());
    }

    /**
     * This test verifies that we have an empty and valid
     * list for new objects.
     */
    #[Group('promotions')]
    public function testCartItemsEmptyOnNewObject(): void
    {
        $package = new DiscountPackage(new LineItemQuantityCollection());

        static::assertEquals(0, $package->getCartItems()->count());
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

        static::assertEquals(1, $package->getCartItems()->count());
    }

    /**
     * This test verifies that we dont get an exception
     * when requesting the price without any items.
     * We have to get 0,00 in this case.
     */
    #[Group('promotions')]
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
     */
    #[Group('promotions')]
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

        static::assertEquals(29, $package->getTotalPrice());
    }

    /**
     * This test verifies that we have an empty and valid
     * list for new objects.
     */
    #[Group('promotions')]
    public function testAffectedPricesOnNewObject(): void
    {
        $package = new DiscountPackage(new LineItemQuantityCollection());

        static::assertEquals(0, $package->getAffectedPrices()->count());
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

        static::assertEquals(2, $package->getAffectedPrices()->count());
    }
}
