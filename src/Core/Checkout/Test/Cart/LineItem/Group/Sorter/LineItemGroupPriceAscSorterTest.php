<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Cart\LineItem\Group\Sorter;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\LineItem\Group\LineItemGroupSorterInterface;
use Shopware\Core\Checkout\Cart\LineItem\Group\Sorter\LineItemGroupPriceAscSorter;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Test\Cart\LineItem\Group\Helpers\Traits\LineItemTestFixtureBehaviour;

class LineItemGroupPriceAscSorterTest extends TestCase
{
    use LineItemTestFixtureBehaviour;

    /**
     * @var LineItemGroupSorterInterface
     */
    private $sorter;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sorter = new LineItemGroupPriceAscSorter();
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
    public function testKey()
    {
        static::assertEquals('PRICE_ASC', $this->sorter->getKey());
    }

    /**
     * This test verifies that our sorting works correctly.
     * We add 3 items with different unit prices and test that
     * the sorted list comes in the correct order.
     *
     * @test
     * @group lineitemgroup
     */
    public function testSortPriceASC()
    {
        $items = new LineItemCollection();
        $items->add($this->createProductItem(50.0, 0));
        $items->add($this->createProductItem(23.5, 0));
        $items->add($this->createProductItem(150.0, 0));

        /** @var LineItemCollection $sortedItems */
        $sortedItems = $this->sorter->sort($items);

        static::assertEquals(23.5, $sortedItems->getFlat()[0]->getPrice()->getUnitPrice());
        static::assertEquals(50.0, $sortedItems->getFlat()[1]->getPrice()->getUnitPrice());
        static::assertEquals(150.0, $sortedItems->getFlat()[2]->getPrice()->getUnitPrice());
    }

    /**
     * This test verifies that our item with PRICE null is sorted
     * before all other items.
     *
     * @test
     * @group lineitemgroup
     */
    public function testSortWithPriceNullA()
    {
        $items = new LineItemCollection();
        $a = $this->createProductItem(50.0, 0);
        $b = $this->createProductItem(23.5, 0);

        $a->setPrice(null);

        $items->add($a);
        $items->add($b);

        /** @var LineItemCollection $sortedItems */
        $sortedItems = $this->sorter->sort($items);

        static::assertSame($a, $sortedItems->getFlat()[0]);
        static::assertSame($b, $sortedItems->getFlat()[1]);
    }

    /**
     * This test verifies that our item with PRICE null is sorted
     * before all other items.
     *
     * @test
     * @group lineitemgroup
     */
    public function testSortWithPriceNullB()
    {
        $items = new LineItemCollection();
        $a = $this->createProductItem(50.0, 0);
        $b = $this->createProductItem(23.5, 0);

        $b->setPrice(null);

        $items->add($a);
        $items->add($b);

        /** @var LineItemCollection $sortedItems */
        $sortedItems = $this->sorter->sort($items);

        static::assertSame($b, $sortedItems->getFlat()[0]);
        static::assertSame($a, $sortedItems->getFlat()[1]);
    }
}
