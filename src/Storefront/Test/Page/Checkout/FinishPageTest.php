<?php declare(strict_types=1);

namespace Shopware\Storefront\Test\Page\Checkout;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Exception\OrderNotFoundException;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Storefront\Page\Checkout\Finish\CheckoutFinishPage;
use Shopware\Storefront\Page\Checkout\Finish\CheckoutFinishPageLoadedEvent;
use Shopware\Storefront\Page\Checkout\Finish\CheckoutFinishPageLoader;
use Shopware\Storefront\Test\Page\StorefrontPageTestBehaviour;
use Symfony\Component\HttpFoundation\Request;

class FinishPageTest extends TestCase
{
    use IntegrationTestBehaviour;
    use StorefrontPageTestBehaviour;

    public function testItRequiresAOrderId(): void
    {
        $request = new Request();
        $context = $this->createSalesChannelContextWithLoggedInCustomerAndWithNavigation();

        $this->expectParamMissingException('orderId');
        $this->getPageLoader()->load($request, $context);
    }

    public function testMissingOrderThrows(): void
    {
        $request = new Request([], [], ['orderId' => 'foo']);
        $context = $this->createSalesChannelContextWithLoggedInCustomerAndWithNavigation();

        $this->expectException(OrderNotFoundException::class);
        $this->getPageLoader()->load($request, $context);
    }

    public function testFinishPageLoading(): void
    {
        $context = $this->createSalesChannelContextWithLoggedInCustomerAndWithNavigation();
        $orderId = $this->placeRandomOrder($context);
        $request = new Request([], [], ['orderId' => $orderId]);

        /** @var CheckoutFinishPageLoadedEvent $event */
        $event = null;
        $this->catchEvent(CheckoutFinishPageLoadedEvent::class, $event);

        $page = $this->getPageLoader()->load($request, $context);

        static::assertInstanceOf(CheckoutFinishPage::class, $page);
        static::assertSame(13.04, $page->getOrder()->getPrice()->getNetPrice());
        self::assertPageEvent(CheckoutFinishPageLoadedEvent::class, $event, $context, $request, $page);
    }

    /**
     * @return CheckoutFinishPageLoader
     */
    protected function getPageLoader()
    {
        return $this->getContainer()->get(CheckoutFinishPageLoader::class);
    }
}
