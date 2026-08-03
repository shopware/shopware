<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Page\Account\Order;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Order\SalesChannel\AbstractOrderRoute;
use Shopware\Core\Checkout\Order\SalesChannel\OrderRouteResponse;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Annotation\DisabledFeatures;
use Shopware\Core\Test\Generator;
use Shopware\Core\Test\Stub\EventDispatcher\CollectingEventDispatcher;
use Shopware\Storefront\Event\RouteRequest\OrderRouteRequestEvent;
use Shopware\Storefront\Page\Account\Order\AccountOrderDetailPageLoadedEvent;
use Shopware\Storefront\Page\Account\Order\AccountOrderDetailPageLoader;
use Shopware\Storefront\Page\GenericPageLoaderInterface;
use Shopware\Storefront\Page\MetaInformation;
use Shopware\Storefront\Page\Page;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The loader is deprecated for v6.8; remove this test with it.
 *
 * @internal
 */
#[Package('checkout')]
#[CoversClass(AccountOrderDetailPageLoader::class)]
#[DisabledFeatures(['v6.8.0.0'])]
class AccountOrderDetailPageLoaderTest extends TestCase
{
    private CollectingEventDispatcher $eventDispatcher;

    private AbstractOrderRoute&MockObject $orderRoute;

    private GenericPageLoaderInterface&MockObject $genericPageLoader;

    private AccountOrderDetailPageLoader $pageLoader;

    protected function setUp(): void
    {
        $this->eventDispatcher = new CollectingEventDispatcher();
        $this->orderRoute = $this->createMock(AbstractOrderRoute::class);
        $this->genericPageLoader = $this->createMock(GenericPageLoaderInterface::class);

        $this->pageLoader = new AccountOrderDetailPageLoader(
            $this->genericPageLoader,
            $this->eventDispatcher,
            $this->orderRoute,
        );
    }

    public function testLoadPutsTheOrderOnThePage(): void
    {
        $order = new OrderEntity();
        $order->setId(Uuid::randomHex());

        $this->orderRoute
            ->expects($this->once())
            ->method('load')
            ->willReturn($this->orderResponse(new OrderCollection([$order])));

        $page = new Page();
        $page->setMetaInformation(new MetaInformation());

        $this->genericPageLoader
            ->expects($this->once())
            ->method('load')
            ->willReturn($page);

        $detailPage = $this->pageLoader->load(new Request(['id' => $order->getId()]), Generator::generateSalesChannelContext());

        static::assertSame($order, $detailPage->getOrder());
        static::assertSame('noindex,follow', $detailPage->getMetaInformation()?->getRobots());

        $events = $this->eventDispatcher->getEvents();
        static::assertCount(2, $events);
        static::assertInstanceOf(OrderRouteRequestEvent::class, $events[0]);
        static::assertInstanceOf(AccountOrderDetailPageLoadedEvent::class, $events[1]);
    }

    public function testLoadThrowsNotFoundWhenTheOrderDoesNotExist(): void
    {
        $this->orderRoute
            ->expects($this->once())
            ->method('load')
            ->willReturn($this->orderResponse(new OrderCollection()));

        $this->genericPageLoader
            ->expects($this->never())
            ->method('load');

        $this->expectException(NotFoundHttpException::class);

        $this->pageLoader->load(new Request(['id' => Uuid::randomHex()]), Generator::generateSalesChannelContext());
    }

    private function orderResponse(OrderCollection $orders): OrderRouteResponse
    {
        return new OrderRouteResponse(
            EntitySearchResult::create(
                $orders->count(),
                $orders,
                null,
                new Criteria(),
                Context::createDefaultContext(),
            ),
        );
    }
}
