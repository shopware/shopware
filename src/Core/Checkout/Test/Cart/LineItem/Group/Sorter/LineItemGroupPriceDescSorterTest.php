<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Cart\LineItem\Group\Sorter;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\LineItem\Group\LineItemGroupSorterInterface;
use Shopware\Core\Checkout\Cart\LineItem\Group\Sorter\LineItemGroupPriceDescSorter;
use Shopware\Core\Checkout\Cart\LineItem\LineItemFlatCollection;
use Shopware\Core\Checkout\Test\Cart\LineItem\Group\Helpers\Traits\LineItemTestFixtureBehaviour;

class LineItemGroupPriceDescSorterTest extends TestCase
{
    use LineItemTestFixtureBehaviour;

    /**
     * @var LineItemGroupSorterInterface
     */
    private $sorter;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sorter = new LineItemGroupPriceDescSorter();
    }

    /**
     * This test verifies that our key identifier is not touched without recognizing it.
     * Please keep in mind, if you change the identifier, there might still
     * be old keys in the SetGroup entities in the database of shops, that
     * try to execute a sorter that does not exist anymore with this key.
     *
     * @test
     * @group lineitemgroup
     */
    public function testKey(): void
    {
        static::assertEquals('PRICE_DESC', $this->sorter->getKey());
    }

    /**
     * This test verifies that our sorting works correctly.
     * We add 3 items with different item prices and test that
     * the sorted list comes in the correct order.
     *
     * @test
     * @group lineitemgroup
     */
    public function testSortPriceDESC(): void
    {
        $p1 = $this->createProductItem(50.0, 0);
        $p2 = $this->createProductItem(23.5, 0);
        $p3 = $this->createProductItem(150.0, 0);

        $items = new LineItemFlatCollection();
        $items->add($p1);
        $items->add($p2);
        $items->add($p3);

        $sortedItems = $this->sorter->sort($items);

        static::assertEquals($p3->getId(), $sortedItems->getElements()[0]->getId());
        static::assertEquals($p1->getId(), $sortedItems->getElements()[1]->getId());
        static::assertEquals($p2->getId(), $sortedItems->getElements()[2]->getId());
    }

    /**
     * This test verifies that our item with PRICE null is sorted
     * after all other items.
     *
     * @test
     * @group lineitemgroup
     */
    public function testSortWithPriceNullA(): void
    {
        $items = new LineItemFlatCollection();
        $a = $this->createProductItem(50.0, 0);
        $b = $this->createProductItem(23.5, 0);

        $a->setPrice(null);

        $items->add($a);
        $items->add($b);

        $sortedItems = $this->sorter->sort($items);

        static::assertSame($b, $sortedItems->getElements()[0]);
        static::assertSame($a, $sortedItems->getElements()[1]);
    }

    /**
     * This test verifies that our item with PRICE null is sorted
     * after all other items.
     *
     * @test
     * @group lineitemgroup
     */
    public function testSortWithPriceNullB(): void
    {
        $items = new LineItemFlatCollection();
        $a = $this->createProductItem(50.0, 0);
        $b = $this->createProductItem(23.5, 0);

        $b->setPrice(null);

        $items->add($a);
        $items->add($b);

        $sortedItems = $this->sorter->sort($items);

        static::assertSame($a, $sortedItems->getElements()[0]);
        static::assertSame($b, $sortedItems->getElements()[1]);
    }
}
