<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart\Promotion\Cart\Discount\Filter\Applier;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Promotion\Cart\Discount\Filter\Applier\Applier;

/**
 * @internal
 */
#[CoversClass(Applier::class)]
class ApplierTest extends TestCase
{
    /**
     * This test verifies that we get an empty
     * array of indexes if we want to use all cart items.
     * Empty array means, use all indexes.
     */
    #[Group('promotions')]
    public function testCartAllItems(): void
    {
        $applier = new Applier();

        $indexes = $applier->findIndexes(
            'ALL',
            -1,
            1,
            1
        );

        $expected = [];

        static::assertEquals($expected, $indexes);
    }

    /**
     * This test verifies that we only get index
     * 0 and 1 (first 2 items) if we have a cart (1 package)
     * that has no appliers, but a max item limitation.
     */
    #[Group('promotions')]
    public function testCart2Items(): void
    {
        $applier = new Applier();

        $indexes = $applier->findIndexes(
            'ALL',
            2,
            1,
            1
        );

        $expected = [
            0,
            1,
        ];

        static::assertEquals($expected, $indexes);
    }

    /**
     * This test verifies that we get the second
     * item of our packages. In this test we have
     * used a picker to restructure our packages from
     * 3 packages into 1 single package.
     * The result should be 3 items (because we have 3 packages),
     * out of the second PACKAGE.
     * This is index 3, 4 and 5.
     */
    #[Group('promotions')]
    public function testSetGroupHorizontalItems(): void
    {
        $applier = new Applier();

        $indexes = $applier->findIndexes(
            '2',
            -1,
            1,
            3
        );

        $expected = [
            3,
            4,
            5,
        ];

        static::assertEquals($expected, $indexes);
    }

    /**
     * This test verifies should limit the 3 found packages
     * of our test to a maximum of 2 items.
     * The applier should find the second package with 3 items
     * (because we have 3 packages).
     * But only use 2 of the items, which leads to index 3 + 4.
     */
    #[Group('promotions')]
    public function testSetGroupHorizontalItemsMax2(): void
    {
        $applier = new Applier();

        $indexes = $applier->findIndexes(
            '2',
            2,
            1,
            3
        );

        $expected = [
            3,
            4,
        ];

        static::assertEquals($expected, $indexes);
    }
}
