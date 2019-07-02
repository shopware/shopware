<?php declare(strict_types=1);

namespace Shopware\Storefront\Test\Page\Checkout;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Storefront\Page\Checkout\Cart\CheckoutCartPage;
use Shopware\Storefront\Page\Checkout\Cart\CheckoutCartPageLoadedEvent;
use Shopware\Storefront\Page\Checkout\Cart\CheckoutCartPageLoader;
use Shopware\Storefront\Test\Page\StorefrontPageTestBehaviour;
use Symfony\Component\HttpFoundation\Request;

class CartPageTest extends TestCase
{
    use IntegrationTestBehaviour;
    use StorefrontPageTestBehaviour;

    public function testItLoadsTheCart(): void
    {
        $request = new Request();
        $context = $this->createSalesChannelContextWithNavigation();

        /** @var CheckoutCartPageLoadedEvent $event */
        $event = null;
        $this->catchEvent(CheckoutCartPageLoadedEvent::class, $event);

        $page = $this->getPageLoader()->load($request, $context);

        static::assertInstanceOf(CheckoutCartPage::class, $page);
        static::assertSame(0.0, $page->getCart()->getPrice()->getNetPrice());
        static::assertSame($context->getToken(), $page->getCart()->getToken());
        self::assertPageEvent(CheckoutCartPageLoadedEvent::class, $event, $context, $request, $page);
    }

    /**
     * @return CheckoutCartPageLoader
     */
    protected function getPageLoader()
    {
        return $this->getContainer()->get(CheckoutCartPageLoader::class);
    }
}
