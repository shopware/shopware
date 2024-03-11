<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart\Facade;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\CartException;
use Shopware\Core\Checkout\Cart\Facade\CartFacadeHelper;
use Shopware\Core\Checkout\Cart\Facade\ContainerFacade;
use Shopware\Core\Checkout\Cart\Facade\ItemFacade;
use Shopware\Core\Checkout\Cart\Facade\ScriptPriceStubs;
use Shopware\Core\Checkout\Cart\Facade\Traits\DiscountTrait;
use Shopware\Core\Checkout\Cart\Facade\Traits\SurchargeTrait;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Price\Struct\CurrencyPriceDefinition;
use Shopware\Core\Checkout\Cart\Price\Struct\PercentagePriceDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\Price;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\PriceCollection;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @internal
 */
#[CoversClass(ContainerFacade::class)]
#[CoversClass(DiscountTrait::class)]
#[CoversClass(SurchargeTrait::class)]
class ContainerFacadeTest extends TestCase
{
    public function testPublicApiAvailable(): void
    {
        $facade = $this->rampUpFacade();

        static::assertEquals('container', $facade->getType());
        static::assertEquals('container', $facade->getId());
        static::assertEquals('container', $facade->getReferencedId());

        static::assertEquals(1, $facade->getQuantity());
        static::assertTrue($facade->has('foo'));

        $facade->remove('foo');
        static::assertFalse($facade->has('foo'));
        static::assertCount(0, $facade->products());
    }

    public function testAbsoluteDiscount(): void
    {
        $facade = $this->rampUpFacade();

        static::assertEquals(1, $facade->getQuantity());
        static::assertTrue($facade->has('foo'));

        $absolute = new PriceCollection([new Price(Defaults::CURRENCY, 5, 5, false)]);
        $facade->discount('absolute', 'absolute', $absolute, 'my-discount');

        static::assertEquals(1, $facade->getQuantity());
        static::assertTrue($facade->has('absolute'));

        $discount = $facade->get('absolute');
        static::assertInstanceOf(ItemFacade::class, $discount);
        static::assertEquals('discount', $discount->getType());
        static::assertNull($discount->getPrice());

        $definition = $discount->getItem()->getPriceDefinition();
        static::assertInstanceOf(CurrencyPriceDefinition::class, $definition);
        static::assertEquals($absolute, $definition->getPrice());
    }

    public function testPercentageDiscount(): void
    {
        $facade = $this->rampUpFacade();

        static::assertEquals(1, $facade->getQuantity());
        static::assertTrue($facade->has('foo'));

        $facade->discount('percentage', 'percentage', 10, 'my-discount');

        static::assertEquals(1, $facade->getQuantity());
        static::assertTrue($facade->has('percentage'));

        $discount = $facade->get('percentage');
        static::assertInstanceOf(ItemFacade::class, $discount);
        static::assertEquals('discount', $discount->getType());
        static::assertNull($discount->getPrice());

        $definition = $discount->getItem()->getPriceDefinition();
        static::assertInstanceOf(PercentagePriceDefinition::class, $definition);
    }

    public function testDiscountRequiresDefaultCurrency(): void
    {
        $facade = $this->rampUpFacade();

        $this->expectException(CartException::class);
        $this->expectExceptionMessage('Absolute discount my-discount requires a defined currency price for the default currency. Use services.price(...) to create a compatible price object');

        $facade->discount('my-discount', 'absolute', new PriceCollection([new Price(Uuid::randomHex(), 5, 5, false)]), 'my-discount');
    }

    public function testNotSupportedDiscountType(): void
    {
        $facade = $this->rampUpFacade();

        $this->expectException(CartException::class);
        $this->expectExceptionMessage('Discount type "foo" is not supported');

        $facade->discount('my-discount', 'foo', 10, 'my-discount');
    }

    public function testAbsoluteSurcharge(): void
    {
        $facade = $this->rampUpFacade();

        static::assertEquals(1, $facade->getQuantity());
        static::assertTrue($facade->has('foo'));

        $absolute = new PriceCollection([new Price(Defaults::CURRENCY, 5, 5, false)]);
        $facade->surcharge('absolute', 'absolute', $absolute, 'my-surcharge');

        static::assertEquals(1, $facade->getQuantity());
        static::assertTrue($facade->has('absolute'));

        $surcharge = $facade->get('absolute');
        static::assertInstanceOf(ItemFacade::class, $surcharge);
        static::assertEquals('discount', $surcharge->getType());
        static::assertNull($surcharge->getPrice());

        $definition = $surcharge->getItem()->getPriceDefinition();
        static::assertInstanceOf(CurrencyPriceDefinition::class, $definition);
        static::assertEquals($absolute, $definition->getPrice());
    }

    public function testPercentageSurcharge(): void
    {
        $facade = $this->rampUpFacade();

        static::assertEquals(1, $facade->getQuantity());
        static::assertTrue($facade->has('foo'));

        $facade->surcharge('percentage', 'percentage', 10, 'my-surcharge');

        static::assertEquals(1, $facade->getQuantity());
        static::assertTrue($facade->has('percentage'));

        $surcharge = $facade->get('percentage');
        static::assertInstanceOf(ItemFacade::class, $surcharge);
        static::assertEquals('discount', $surcharge->getType());
        static::assertNull($surcharge->getPrice());

        $definition = $surcharge->getItem()->getPriceDefinition();
        static::assertInstanceOf(PercentagePriceDefinition::class, $definition);
    }

    public function testSurchargeRequiresDefaultCurrency(): void
    {
        $facade = $this->rampUpFacade();

        $this->expectException(CartException::class);
        $this->expectExceptionMessage('Absolute surcharge my-surcharge requires a defined currency price for the default currency. Use services.price(...) to create a compatible price object');

        $facade->surcharge('my-surcharge', 'absolute', new PriceCollection([new Price(Uuid::randomHex(), 5, 5, false)]), 'my-surcharge');
    }

    public function testNotSupportedSurchargeType(): void
    {
        $facade = $this->rampUpFacade();

        $this->expectException(CartException::class);
        $this->expectExceptionMessage('Surcharge type "foo" is not supported');

        $facade->surcharge('my-surcharge', 'foo', 10, 'my-surcharge');
    }

    private function rampUpFacade(): ContainerFacade
    {
        $container = new LineItem('container', 'container', 'container');

        $stubs = $this->createMock(ScriptPriceStubs::class);
        $helper = $this->createMock(CartFacadeHelper::class);
        $context = $this->createMock(SalesChannelContext::class);
        $facade = new ContainerFacade($container, $stubs, $helper, $context);

        $facade->add(
            new ItemFacade(new LineItem('foo', 'foo', 'foo'), $stubs, $helper, $context)
        );

        return $facade;
    }
}
