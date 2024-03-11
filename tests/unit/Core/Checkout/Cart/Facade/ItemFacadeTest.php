<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart\Facade;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Facade\CartFacadeHelper;
use Shopware\Core\Checkout\Cart\Facade\ItemFacade;
use Shopware\Core\Checkout\Cart\Facade\PriceFacade;
use Shopware\Core\Checkout\Cart\Facade\ScriptPriceStubs;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @internal
 */
#[CoversClass(ItemFacade::class)]
class ItemFacadeTest extends TestCase
{
    public function testPublicApiAvailable(): void
    {
        $item = new LineItem('foo', 'type', 'reference', 2);
        $item->setLabel('label');
        $item->setPayload([
            'foo' => 'bar',
            'nested' => ['foo' => 'nested'],
        ]);

        $price = new CalculatedPrice(10, 10, new CalculatedTaxCollection(), new TaxRuleCollection());
        $item->setPrice($price);

        $stubs = $this->createMock(ScriptPriceStubs::class);
        $helper = $this->createMock(CartFacadeHelper::class);
        $context = $this->createMock(SalesChannelContext::class);

        $facade = new ItemFacade($item, $stubs, $helper, $context);

        static::assertEquals('foo', $facade->getId());
        static::assertEquals('type', $facade->getType());
        static::assertEquals('reference', $facade->getReferencedId());
        static::assertEquals('label', $facade->getLabel());
        static::assertEquals(2, $facade->getQuantity());

        static::assertInstanceOf(PriceFacade::class, $facade->getPrice());
        static::assertEquals(10, $facade->getPrice()->getUnit());
        static::assertEquals(10, $facade->getPrice()->getTotal());

        static::assertEquals('bar', $facade->getPayload()->offsetGet('foo'));
        // @phpstan-ignore-next-line
        static::assertEquals('bar', $facade->getPayload()['foo']);
        static::assertEquals('nested', $facade->getPayload()->offsetGet('nested')['foo']);

        // @phpstan-ignore-next-line
        static::assertEquals('nested', $facade->getPayload()['nested']['foo']);

        static::assertCount(0, $facade->getChildren());
    }

    public function testICanTakeItems(): void
    {
        $item = new LineItem('foo', 'type', 'reference', 5);
        $item->setLabel('label');
        $item->setStackable(true);

        $stubs = $this->createMock(ScriptPriceStubs::class);
        $helper = $this->createMock(CartFacadeHelper::class);
        $context = $this->createMock(SalesChannelContext::class);

        $facade = new ItemFacade($item, $stubs, $helper, $context);

        $new = $facade->take(2);

        static::assertInstanceOf(ItemFacade::class, $new);

        static::assertEquals(2, $new->getQuantity());
        static::assertEquals(3, $facade->getQuantity());
        static::assertNotEquals($facade->getId(), $new->getId());
        static::assertEquals($facade->getType(), $new->getType());
        static::assertEquals($facade->getReferencedId(), $new->getReferencedId());
        static::assertEquals($facade->getLabel(), $new->getLabel());

        static::assertNull($facade->take(100));
    }

    public function testICanNotTakeNoneStackable(): void
    {
        $item = new LineItem('foo', 'type', 'reference', 5);
        $item->setLabel('label');
        $item->setStackable(false);

        $stubs = $this->createMock(ScriptPriceStubs::class);
        $helper = $this->createMock(CartFacadeHelper::class);
        $context = $this->createMock(SalesChannelContext::class);

        $facade = new ItemFacade($item, $stubs, $helper, $context);

        static::assertNull($facade->take(2));
    }

    public function testIWillGetNoPriceFacadeIfItemHasNoPrice(): void
    {
        $item = new LineItem('foo', 'type', 'reference', 5);

        $stubs = $this->createMock(ScriptPriceStubs::class);
        $helper = $this->createMock(CartFacadeHelper::class);
        $context = $this->createMock(SalesChannelContext::class);

        $facade = new ItemFacade($item, $stubs, $helper, $context);

        static::assertNull($facade->getPrice());
    }
}
