<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Page\Checkout;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\Hook\CartAware;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Generator;
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

/**
 * @internal
 */
#[CoversClass(CheckoutCartPageLoadedHook::class)]
#[CoversClass(CheckoutConfirmPageLoadedHook::class)]
#[CoversClass(CheckoutInfoWidgetLoadedHook::class)]
#[CoversClass(CheckoutOffcanvasWidgetLoadedHook::class)]
#[CoversClass(CheckoutRegisterPageLoadedHook::class)]
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

    #[DataProvider('dataProviderHooks')]
    public function testNameRespectsCartSource(PageLoadedHook&CartAware $hook): void
    {
        $hook->getCart()->setSource('test');

        static::assertStringEndsWith('-loaded-test', $hook->getName());
    }

    #[DataProvider('dataProviderHooks')]
    public function testNameWithoutCartSource(PageLoadedHook&CartAware $hook): void
    {
        static::assertStringEndsWith('-loaded', $hook->getName());
    }
}
