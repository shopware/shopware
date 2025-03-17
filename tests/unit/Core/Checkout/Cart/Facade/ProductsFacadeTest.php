<?php

declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart\Facade;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Facade\CartFacadeHelper;
use Shopware\Core\Checkout\Cart\Facade\ItemFacade;
use Shopware\Core\Checkout\Cart\Facade\ProductsFacade;
use Shopware\Core\Checkout\Cart\Facade\ScriptPriceStubs;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @internal
 */
#[CoversClass(ProductsFacade::class)]
class ProductsFacadeTest extends TestCase
{
    private ScriptPriceStubs $stubs;

    private CartFacadeHelper&MockObject $helper;

    private SalesChannelContext $context;

    protected function setUp(): void
    {
        $this->stubs = $this->createMock(ScriptPriceStubs::class);
        $this->helper = $this->createMock(CartFacadeHelper::class);
        $this->context = $this->createMock(SalesChannelContext::class);
    }

    public function testGetWithNonExistingProduct(): void
    {
        $items = new LineItemCollection();
        $facade = $this->createFacade($items);

        static::assertNull($facade->get(Uuid::randomHex()));
    }

    public function testGetWithExistingLineItemButNotOfTypeProduct(): void
    {
        $productId = Uuid::randomHex();
        $facade = $this->createFacade(new LineItemCollection([
            new LineItem($productId, LineItem::PROMOTION_LINE_ITEM_TYPE),
        ]));

        static::assertNull($facade->get($productId));
    }

    public function testGetWithExistingProduct(): void
    {
        $productId = Uuid::randomHex();
        $facade = $this->createFacade(new LineItemCollection([
            new LineItem($productId, LineItem::PRODUCT_LINE_ITEM_TYPE),
        ]));

        static::assertNotNull($facade->get($productId));
    }

    public function testAddWithLineItem(): void
    {
        $items = new LineItemCollection();
        $facade = $this->createFacade($items);
        $productId = Uuid::randomHex();

        $facade->add(new LineItem($productId, LineItem::PRODUCT_LINE_ITEM_TYPE));

        static::assertTrue($items->has($productId));
    }

    public function testAddWithItemFacade(): void
    {
        $items = new LineItemCollection();
        $facade = $this->createFacade($items);
        $productId = Uuid::randomHex();

        $facade->add(new ItemFacade(new LineItem($productId, LineItem::PRODUCT_LINE_ITEM_TYPE), $this->stubs, $this->helper, $this->context));

        static::assertTrue($items->has($productId));
    }

    public function testAddWithProductIdAndQuantity(): void
    {
        $items = new LineItemCollection();
        $facade = $this->createFacade($items);
        $productId = Uuid::randomHex();
        $this->helper->method('product')->willReturn(new LineItem($productId, LineItem::PRODUCT_LINE_ITEM_TYPE));

        $facade->add($productId);

        static::assertTrue($items->has($productId));
    }

    public function testCreate(): void
    {
        $productId = Uuid::randomHex();
        $facade = $this->createFacade(new LineItemCollection());
        $this->helper->method('product')->willReturn(new LineItem($productId, LineItem::PRODUCT_LINE_ITEM_TYPE));

        $item = $facade->create($productId);
        static::assertSame($productId, $item->getId());
    }

    private function createFacade(LineItemCollection $items): ProductsFacade
    {
        return new ProductsFacade($items, $this->stubs, $this->helper, $this->context);
    }
}
