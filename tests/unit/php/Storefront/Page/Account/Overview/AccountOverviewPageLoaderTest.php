<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Page\Account\Overview;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\SalesChannel\CustomerRoute;
use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Order\SalesChannel\OrderRoute;
use Shopware\Core\Checkout\Order\SalesChannel\OrderRouteResponse;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Event\RouteRequest\OrderRouteRequestEvent;
use Shopware\Storefront\Page\Account\Overview\AccountOverviewPage;
use Shopware\Storefront\Page\Account\Overview\AccountOverviewPageLoadedEvent;
use Shopware\Storefront\Page\Account\Overview\AccountOverviewPageLoader;
use Shopware\Storefront\Page\GenericPageLoader;
use Shopware\Storefront\Pagelet\Newsletter\Account\NewsletterAccountPageletLoader;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 *
 * @covers \Shopware\Storefront\Page\Account\Overview\AccountOverviewPageLoader
 */
class AccountOverviewPageLoaderTest extends TestCase
{
    /**
     * @var EventDispatcherInterface&MockObject
     */
    private EventDispatcherInterface $eventDispatcher;

    /**
     * @var OrderRoute&MockObject
     */
    private OrderRoute $orderRoute;

    private AccountOverviewPageLoader $pageLoader;

    protected function setUp(): void
    {
        $this->eventDispatcher = $this->createMock(EventDispatcher::class);

        $this->orderRoute = $this->createMock(OrderRoute::class);

        $this->pageLoader = new AccountOverviewPageLoader(
            $this->createMock(GenericPageLoader::class),
            $this->eventDispatcher,
            $this->orderRoute,
            $this->createMock(CustomerRoute::class),
            $this->createMock(NewsletterAccountPageletLoader::class)
        );
    }

    public function testLoad(): void
    {
        $order = (new OrderEntity())->assign(['_uniqueIdentifier' => Uuid::randomHex()]);

        $orders = new OrderCollection([$order]);

        $orderResponse = new OrderRouteResponse(
            new EntitySearchResult(
                OrderDefinition::ENTITY_NAME,
                1,
                $orders,
                null,
                new Criteria(),
                Context::createDefaultContext()
            )
        );

        $this->orderRoute
            ->expects(static::once())
            ->method('load')
            ->willReturn($orderResponse);

        $this->eventDispatcher
            ->expects(static::exactly(2))
            ->method('dispatch')
            ->withConsecutive(
                [static::isInstanceOf(OrderRouteRequestEvent::class)],
                [static::isInstanceOf(AccountOverviewPageLoadedEvent::class)],
            );

        $customer = new CustomerEntity();
        $page = $this->pageLoader->load(new Request(), $this->createMock(SalesChannelContext::class), $customer);

        static::assertInstanceOf(AccountOverviewPage::class, $page);
        static::assertEquals($order, $page->getNewestOrder());
    }
}
