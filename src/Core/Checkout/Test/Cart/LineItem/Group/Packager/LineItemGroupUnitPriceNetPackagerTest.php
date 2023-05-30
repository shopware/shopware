<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Cart\LineItem\Group\Packager;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\LineItem\Group\LineItemGroup;
use Shopware\Core\Checkout\Cart\LineItem\Group\LineItemGroupPackagerInterface;
use Shopware\Core\Checkout\Cart\LineItem\Group\Packager\LineItemGroupUnitPriceNetPackager;
use Shopware\Core\Checkout\Cart\LineItem\LineItemFlatCollection;
use Shopware\Core\Checkout\Test\Cart\LineItem\Group\Helpers\Traits\LineItemTestFixtureBehaviour;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @internal
 */
#[Package('checkout')]
class LineItemGroupUnitPriceNetPackagerTest extends TestCase
{
    use LineItemTestFixtureBehaviour;

    private LineItemGroupPackagerInterface $packager;

    private MockObject&SalesChannelContext $context;

    protected function setUp(): void
    {
        parent::setUp();

        $this->packager = new LineItemGroupUnitPriceNetPackager();

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
        static::assertEquals('PRICE_UNIT_NET', $this->packager->getKey());
    }

    /**
     * This test verifies that we have finished building our
     * package, as soon as we have reached a gross item price of 100.0.
     * This is archived with 4 items.
     *
     * @group lineitemgroup
     */
    public function testPackageDoneWhenSumReached(): void
    {
        $items = new LineItemFlatCollection();
        $items->add($this->createProductItem(25.0, 19));
        $items->add($this->createProductItem(25.0, 19));
        $items->add($this->createProductItem(25.0, 19));
        $items->add($this->createProductItem(25.0, 19));
        $items->add($this->createProductItem(25.0, 19));
        $items->add($this->createProductItem(25.0, 19));

        $packageItems = $this->packager->buildGroupPackage(100, $items, $this->context);

        // verify we have 4 items, then we have reached 100.0
        static::assertCount(4, $packageItems->getItems());
    }

    /**
     * This test verifies that our packager returns an empty result
     * if we dont have enough items to fill a group.
     *
     * @group lineitemgroup
     */
    public function testResultEmptyIfNotEnoughItems(): void
    {
        $items = new LineItemFlatCollection();
        $items->add($this->createProductItem(25.0, 19));
        $items->add($this->createProductItem(25.0, 19));
        $items->add($this->createProductItem(25.0, 19));
        $items->add($this->createProductItem(24.9, 19));

        $packageItems = $this->packager->buildGroupPackage(100, $items, $this->context);

        // verify we have no results because min sum is not reached
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

        $packageItems = $this->packager->buildGroupPackage(100, $items, $this->context);

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

        $packageItems = $this->packager->buildGroupPackage(-100, $items, $this->context);

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
     * This test verifies, that our packager does ignore products
     * that have no calculated price.
     *
     * @group lineitemgroup
     */
    public function testPriceNullIsIgnored(): void
    {
        $items = new LineItemFlatCollection();

        $productNoPrice = $this->createProductItem(20.0, 19);
        $productNoPrice->setPrice(null);

        $items->add($productNoPrice);
        $items->add($this->createProductItem(20.0, 19));

        $packageItems = $this->packager->buildGroupPackage(5, $items, $this->context);

        static::assertCount(1, $packageItems->getItems());
    }
}
