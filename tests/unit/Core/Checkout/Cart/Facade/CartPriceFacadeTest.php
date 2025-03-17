<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart\Facade;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Facade\CartPriceFacade;
use Shopware\Core\Checkout\Cart\Facade\ScriptPriceStubs;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\PriceCollection;

/**
 * @internal
 */
#[CoversClass(CartPriceFacade::class)]
class CartPriceFacadeTest extends TestCase
{
    public function testPublicApiAvailable(): void
    {
        $original = new CartPrice(
            100,
            200,
            300,
            new CalculatedTaxCollection(),
            new TaxRuleCollection(),
            CartPrice::TAX_STATE_GROSS,
            99.99
        );

        $stubs = $this->createMock(ScriptPriceStubs::class);
        $price = new PriceCollection([]);

        $stubs->method('build')->willReturn($price);

        $facade = new CartPriceFacade($original, $stubs);

        static::assertSame(100.0, $facade->getNet());
        static::assertSame(200.0, $facade->getTotal());
        static::assertSame(200.0, $facade->getRounded());
        static::assertSame(300.0, $facade->getPosition());
        static::assertSame(99.99, $facade->getRaw());

        static::assertSame($price, $facade->create([]));
    }
}
