<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Cart\LineItem\Group\Packager;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\LineItem\Group\LineItemGroup;
use Shopware\Core\Checkout\Cart\LineItem\Group\LineItemGroupPackagerInterface;
use Shopware\Core\Checkout\Cart\LineItem\Group\Packager\LineItemGroupCountPackager;
use Shopware\Core\Checkout\Cart\LineItem\LineItemFlatCollection;
use Shopware\Core\Checkout\Test\Cart\LineItem\Group\Helpers\Traits\LineItemTestFixtureBehaviour;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @internal
 */
#[Package('checkout')]
class LineItemGroupCountPackagerTest extends TestCase
{
    use LineItemTestFixtureBehaviour;

    private LineItemGroupPackagerInterface $packager;

    private MockObject&SalesChannelContext $context;

    protected function setUp(): void
    {
        parent::setUp();

        $this->packager = new LineItemGroupCountPackager();

        $this->context = $this->getMockBuilder(SalesChannelContext::class)->disableOriginalConstructor()->getMock();
    }

    /**
     * This test verifies that our key identifier is not touched without recognizing it.
     * Please keep in mind, if you change the identifier, there might still
     * be old keys in the SetGroup entities in the database of shops, that
     * try to execute a packager that does not exist anymore with this key.
     *
     * @group lineitemgroup
     */
    public function testKey(): void
    {
        static::assertEquals('COUNT', $this->packager->getKey());
    }

    /**
     * This test verifies that our packaging does correctly
     * return 2 items if we request that, and if more than 2 items exist.
     *
     * @group lineitemgroup
     */
    public function testPackageDoneWhenCountReached(): void
    {
        $p1 = $this->createProductItem(50.0, 0);
        $p2 = $this->createProductItem(23.5, 0);
        $p3 = $this->createProductItem(150.0, 0);

        $items = new LineItemFlatCollection();
        $items->add($p1);
        $items->add($p2);
        $items->add($p3);

        $packageItems = $this->packager->buildGroupPackage(2, $items, $this->context);

        // verify we have only 2 items
        static::assertCount(2, $packageItems->getItems());

        // test that we have the first 2 from our list
        static::assertEquals($p1->getId(), $packageItems->getItems()[0]->getLineItemId());
        static::assertEquals($p2->getId(), $packageItems->getItems()[1]->getLineItemId());
    }

    /**
     * This test verifies, that we do not get any results, if not
     * enough items exist, to build a package.
     *
     * @group lineitemgroup
     */
    public function testNoResultsIfNotEnoughtItems(): void
    {
        $items = new LineItemFlatCollection();
        $items->add($this->createProductItem(50.0, 0));

        $packageItems = $this->packager->buildGroupPackage(2, $items, $this->context);

        // verify we dont have results, because a
        // package of 2 items couldnt be created
        static::assertCount(0, $packageItems->getItems());
    }

    /**
     * This test verifies, that our packager does also work
     * with an empty list of items. We should also get an empty result list.
     *
     * @group lineitemgroup
     */
    public function testNoItemsReturnsEmptyList(): void
    {
        $items = new LineItemFlatCollection();

        $packageItems = $this->packager->buildGroupPackage(2, $items, $this->context);

        static::assertCount(0, $packageItems->getItems());
    }

    /**
     * This test verifies, that our packager does also work
     * with an invalid negative count. In that case we want an empty result list.
     *
     * @group lineitemgroup
     */
    public function testNegativeCountReturnsEmptyList(): void
    {
        $items = new LineItemFlatCollection();

        $packageItems = $this->packager->buildGroupPackage(-1, $items, $this->context);

        static::assertCount(0, $packageItems->getItems());
    }

    /**
     * This test verifies, that our packager does also work
     * with an invalid zero count. In that case we want an empty result list.
     *
     * @group lineitemgroup
     */
    public function testZeroCountReturnsEmptyList(): void
    {
        $items = new LineItemFlatCollection();

        $packageItems = $this->packager->buildGroupPackage(0, $items, $this->context);

        static::assertCount(0, $packageItems->getItems());
    }

    /**
     * This test verifies that we can successfully build a
     * package, if the quantity of the item is high enough.
     * This means we have just 1 single item, but 3 quantities.
     * Our package needs only 2, so we should get 1 package.
     *
     * @group lineitemgroup
     */
    public function testQuantityHigherAsPackage(): void
    {
        $items = new LineItemFlatCollection();

        $product = $this->createProductItem(50.0, 0);
        $product->setQuantity(3);

        $items->add($product);

        $packageItems = $this->packager->buildGroupPackage(2, $items, $this->context);

        static::assertCount(1, $packageItems->getItems());
    }
}
