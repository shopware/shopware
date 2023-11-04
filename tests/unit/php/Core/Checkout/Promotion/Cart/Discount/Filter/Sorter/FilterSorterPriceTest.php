<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Promotion\Cart\Discount\Filter\Sorter;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\LineItem\Group\LineItemQuantity;
use Shopware\Core\Checkout\Cart\LineItem\Group\LineItemQuantityCollection;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemFlatCollection;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Promotion\Cart\Discount\DiscountPackage;
use Shopware\Core\Checkout\Promotion\Cart\Discount\DiscountPackageCollection;
use Shopware\Core\Checkout\Promotion\Cart\Discount\Filter\Sorter\AbstractPriceSorter;
use Shopware\Core\Checkout\Promotion\Cart\Discount\Filter\Sorter\FilterSorterPriceAsc;
use Shopware\Core\Checkout\Promotion\Cart\Discount\Filter\Sorter\FilterSorterPriceDesc;

/**
 * @internal
 *
 * @covers \Shopware\Core\Checkout\Promotion\Cart\Discount\Filter\Sorter\FilterSorterPriceAsc
 * @covers \Shopware\Core\Checkout\Promotion\Cart\Discount\Filter\Sorter\FilterSorterPriceDesc
 */
class FilterSorterPriceTest extends TestCase
{
    /**
     * @dataProvider sortingProvider
     *
     * @param array<LineItem> $items
     * @param array<LineItemQuantity> $meta
     * @param array<string> $expected
     */
    public function testSorting(AbstractPriceSorter $sorter, array $meta, array $items, array $expected): void
    {
        $package = new DiscountPackage(new LineItemQuantityCollection($meta));

        $package->setCartItems(new LineItemFlatCollection($items));

        $sorter->sort(new DiscountPackageCollection([$package]));

        $ordered = $package->getMetaData()->fmap(fn (LineItemQuantity $item) => $item->getLineItemId());

        static::assertEquals($expected, $ordered);
    }

    public static function sortingProvider(): \Generator
    {
        yield 'Test ascending sorting' => [
            new FilterSorterPriceAsc(),
            [
                new LineItemQuantity('a', 1),
                new LineItemQuantity('b', 1),
                new LineItemQuantity('c', 1),
            ],
            [
                self::item('a', 200),
                self::item('b', 100),
                self::item('c', 300),
            ],
            ['b', 'a', 'c'],
        ];

        yield 'Test descending sorting' => [
            new FilterSorterPriceDesc(),
            [
                new LineItemQuantity('a', 1),
                new LineItemQuantity('b', 1),
                new LineItemQuantity('c', 1),
            ],
            [
                self::item('a', 200),
                self::item('b', 100),
                self::item('c', 300),
            ],
            ['c', 'a', 'b'],
        ];

        yield 'Test ascending sorting with duplicate meta items' => [
            new FilterSorterPriceAsc(),
            [
                new LineItemQuantity('a', 1),
                new LineItemQuantity('a', 1),
                new LineItemQuantity('a', 1),
                new LineItemQuantity('b', 1),
                new LineItemQuantity('b', 1),
                new LineItemQuantity('b', 1),
                new LineItemQuantity('b', 1),
                new LineItemQuantity('b', 1),
                new LineItemQuantity('c', 1),
                new LineItemQuantity('c', 1),
                new LineItemQuantity('c', 1),
                new LineItemQuantity('c', 1),
            ],
            [
                self::item('a', 200),
                self::item('b', 100),
                self::item('c', 300),
            ],
            ['b', 'b', 'b', 'b', 'b', 'a', 'a', 'a', 'c', 'c', 'c', 'c'],
        ];
    }

    private static function item(string $id, float $price): LineItem
    {
        $item = new LineItem($id, 'product');
        $item->setPrice(new CalculatedPrice($price, $price, new CalculatedTaxCollection(), new TaxRuleCollection()));

        return $item;
    }
}
