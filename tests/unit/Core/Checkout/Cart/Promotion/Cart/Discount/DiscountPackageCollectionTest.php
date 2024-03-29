<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart\Promotion\Cart\Discount;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\LineItem\Group\LineItemQuantity;
use Shopware\Core\Checkout\Cart\LineItem\Group\LineItemQuantityCollection;
use Shopware\Core\Checkout\Cart\LineItem\LineItemFlatCollection;
use Shopware\Core\Checkout\Promotion\Cart\Discount\DiscountPackage;
use Shopware\Core\Checkout\Promotion\Cart\Discount\DiscountPackageCollection;
use Shopware\Tests\Integration\Core\Checkout\Cart\Promotion\Helpers\Traits\PromotionLineItemTestFixtureBehaviour;

/**
 * @internal
 */
#[CoversClass(DiscountPackageCollection::class)]
class DiscountPackageCollectionTest extends TestCase
{
    use PromotionLineItemTestFixtureBehaviour;

    /**
     * This test verifies that we can add our elements
     * and that the count returns the correct value
     */
    #[Group('promotions')]
    public function testCountReturnsCorrectValue(): void
    {
        $collection = new DiscountPackageCollection(
            [
                new DiscountPackage(new LineItemQuantityCollection()),
                new DiscountPackage(new LineItemQuantityCollection()),
            ]
        );

        static::assertEquals(2, $collection->count());
    }

    /**
     * This test verifies that our object collects the
     * calculated prices from all existing packages.
     */
    #[Group('promotions')]
    public function testAffectedPricesFromAllPackages(): void
    {
        $product1 = $this->createProductItem(29, 19);
        $product2 = $this->createProductItem(14, 19);

        $package1 = new DiscountPackage(new LineItemQuantityCollection());
        $package1->setCartItems(new LineItemFlatCollection([$product1]));

        $package2 = new DiscountPackage(new LineItemQuantityCollection());
        $package2->setCartItems(new LineItemFlatCollection([$product2]));

        $collection = new DiscountPackageCollection([$package1, $package2]);

        static::assertEquals(2, $collection->getAffectedPrices()->count());
    }

    /**
     * This test verifies that our object collects the
     * line items from all existing packages.
     */
    #[Group('promotions')]
    public function testAllLineItemsFromAllPackages(): void
    {
        $package1 = new DiscountPackage(new LineItemQuantityCollection(
            [
                new LineItemQuantity('ABC', 2),
            ]
        ));

        $package2 = new DiscountPackage(new LineItemQuantityCollection(
            [
                new LineItemQuantity('DEF', 3),
            ]
        ));

        $collection = new DiscountPackageCollection([$package1, $package2]);

        static::assertEquals(2, $collection->getAllLineMetaItems()->count());
    }

    /**
     * This test verifies that our object collects the
     * line items from all existing packages.
     */
    #[Group('promotions')]
    public function testPackagesCanBeSplitIntoSinglePackages(): void
    {
        $package1 = new DiscountPackage(new LineItemQuantityCollection(
            [
                new LineItemQuantity('ABC', 2),
                new LineItemQuantity('DEF', 3),
            ]
        ));

        $package2 = new DiscountPackage(new LineItemQuantityCollection(
            [
                new LineItemQuantity('GHJ', 1),
            ]
        ));

        $splitted = (new DiscountPackageCollection([$package1, $package2]))->splitPackages();

        static::assertEquals(3, $splitted->count());

        $package1 = $splitted->getElements()[0];
        $package2 = $splitted->getElements()[1];
        $package3 = $splitted->getElements()[2];

        // now test the content of each package. only 1 item has to be in there
        static::assertEquals(1, $package1->getMetaData()->count());
        static::assertEquals(1, $package2->getMetaData()->count());
        static::assertEquals(1, $package3->getMetaData()->count());
    }
}
