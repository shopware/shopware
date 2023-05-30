<?php declare(strict_types=1);

namespace Shopware\Storefront\Test\Page\Account;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Routing\RoutingException;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Storefront\Event\RouteRequest\OrderRouteRequestEvent;
use Shopware\Storefront\Page\Account\Order\AccountOrderDetailPage;
use Shopware\Storefront\Page\Account\Order\AccountOrderDetailPageLoadedEvent;
use Shopware\Storefront\Page\Account\Order\AccountOrderDetailPageLoader;
use Shopware\Storefront\Page\Account\Order\AccountOrderPageLoadedEvent;
use Shopware\Storefront\Test\Page\StorefrontPageTestBehaviour;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @internal
 */
class OrderDetailPageTest extends TestCase
{
    use IntegrationTestBehaviour;
    use StorefrontPageTestBehaviour;

    public function testItLoadsOrders(): void
    {
        $context = $this->createSalesChannelContextWithLoggedInCustomerAndWithNavigation();
        $orderId = $this->placeRandomOrder($context);

        $request = new Request();
        $request->query->set('id', $orderId);

        /** @var AccountOrderPageLoadedEvent|null $accountOrderDetailEvent */
        $accountOrderDetailEvent = null;
        $this->catchEvent(AccountOrderDetailPageLoadedEvent::class, $accountOrderDetailEvent);

        /** @var OrderRouteRequestEvent|null $orderRequestEvent */
        $orderRequestEvent = null;
        $this->catchEvent(OrderRouteRequestEvent::class, $orderRequestEvent);

        $page = $this->getPageLoader()->load($request, $context);

        static::assertInstanceOf(AccountOrderDetailPage::class, $page);
        static::assertSame($orderId, $page->getOrder()->getId());
        self::assertPageEvent(AccountOrderDetailPageLoadedEvent::class, $accountOrderDetailEvent, $context, $request, $page);

        static::assertNotNull($orderRequestEvent);
        static::assertSame($request, $orderRequestEvent->getStorefrontRequest());
        static::assertSame($context, $orderRequestEvent->getSalesChannelContext());
        static::assertSame($context->getContext(), $orderRequestEvent->getContext());
    }

    public function testMissingOrderIdThrowsException(): void
    {
        $request = new Request();
        $context = $this->createSalesChannelContextWithLoggedInCustomerAndWithNavigation();

        $this->expectException(RoutingException::class);
        $this->getPageLoader()->load($request, $context);
    }

    public function testUnknownOrderThrowsNotFoundHttpException(): void
    {
        $request = new Request();
        $request->query->set('id', Uuid::randomHex());
        $context = $this->createSalesChannelContextWithLoggedInCustomerAndWithNavigation();

        $this->expectException(NotFoundHttpException::class);
        $this->getPageLoader()->load($request, $context);
    }

    protected function getPageLoader(): AccountOrderDetailPageLoader
    {
        return $this->getContainer()->get(AccountOrderDetailPageLoader::class);
    }
}
