<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Page\Checkout;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\Hook\CartAware;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Storefront\Page\Checkout\Cart\CheckoutCartPage;
use Shopware\Storefront\Page\Checkout\Cart\CheckoutCartPageLoadedHook;
use Shopware\Storefront\Page\Checkout\Confirm\CheckoutConfirmPage;
use Shopware\Storefront\Page\Checkout\Confirm\CheckoutConfirmPageLoadedHook;
use Shopware\Storefront\Page\Checkout\Offcanvas\CheckoutInfoWidgetLoadedHook;
use Shopware\Storefront\Page\Checkout\Offcanvas\CheckoutOffcanvasWidgetLoadedHook;
use Shopware\Storefront\Page\Checkout\Offcanvas\OffcanvasCartPage;
use Shopware\Storefront\Page\Checkout\Register\CheckoutRegisterPage;
use Shopware\Storefront\Page\Checkout\Register\CheckoutRegisterPageLoadedHook;
use Shopware\Storefront\Page\PageLoadedHook;
use Shopware\Tests\Unit\Core\Checkout\Cart\Common\Generator;

/**
 * @internal
 *
 * @covers \Shopware\Storefront\Page\Checkout\Cart\CheckoutCartPageLoadedHook
 * @covers \Shopware\Storefront\Page\Checkout\Confirm\CheckoutConfirmPageLoadedHook
 * @covers \Shopware\Storefront\Page\Checkout\Offcanvas\CheckoutInfoWidgetLoadedHook
 * @covers \Shopware\Storefront\Page\Checkout\Offcanvas\CheckoutOffcanvasWidgetLoadedHook
 * @covers \Shopware\Storefront\Page\Checkout\Register\CheckoutRegisterPageLoadedHook
 */
class CheckoutPageLoadedHookTest extends TestCase
{
    /**
     * @return array<array<PageLoadedHook&CartAware>>
     */
    public static function dataProviderHooks(): array
    {
        $salesChannelContext = Generator::createSalesChannelContext();

        return [
            [new CheckoutCartPageLoadedHook((new CheckoutCartPage())->assign(['cart' => new Cart(Uuid::randomHex())]), $salesChannelContext)],
            [new CheckoutConfirmPageLoadedHook((new CheckoutConfirmPage())->assign(['cart' => new Cart(Uuid::randomHex())]), $salesChannelContext)],
            [new CheckoutInfoWidgetLoadedHook((new OffcanvasCartPage())->assign(['cart' => new Cart(Uuid::randomHex())]), $salesChannelContext)],
            [new CheckoutOffcanvasWidgetLoadedHook((new OffcanvasCartPage())->assign(['cart' => new Cart(Uuid::randomHex())]), $salesChannelContext)],
            [new CheckoutRegisterPageLoadedHook((new CheckoutRegisterPage())->assign(['cart' => new Cart(Uuid::randomHex())]), $salesChannelContext)],
        ];
    }

    /**
     * @dataProvider dataProviderHooks
     */
    public function testNameRespectsCartSource(PageLoadedHook&CartAware $hook): void
    {
        $hook->getCart()->setSource('test');

        static::assertStringEndsWith('-loaded-test', $hook->getName());
    }

    /**
     * @dataProvider dataProviderHooks
     */
    public function testNameWithoutCartSource(PageLoadedHook&CartAware $hook): void
    {
        static::assertStringEndsWith('-loaded', $hook->getName());
    }
}
