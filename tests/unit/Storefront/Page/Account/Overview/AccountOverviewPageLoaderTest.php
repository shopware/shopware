<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Page\Account\Overview;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\SalesChannel\CustomerRoute;
use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Order\SalesChannel\OrderRoute;
use Shopware\Core\Checkout\Order\SalesChannel\OrderRouteResponse;
use Shopware\Core\Framework\Adapter\Translation\AbstractTranslator;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\Stub\EventDispatcher\CollectingEventDispatcher;
use Shopware\Storefront\Event\RouteRequest\OrderRouteRequestEvent;
use Shopware\Storefront\Page\Account\Overview\AccountOverviewPage;
use Shopware\Storefront\Page\Account\Overview\AccountOverviewPageLoadedEvent;
use Shopware\Storefront\Page\Account\Overview\AccountOverviewPageLoader;
use Shopware\Storefront\Page\GenericPageLoader;
use Shopware\Storefront\Page\MetaInformation;
use Shopware\Storefront\Page\Page;
use Shopware\Storefront\Pagelet\Newsletter\Account\NewsletterAccountPageletLoader;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(AccountOverviewPageLoader::class)]
class AccountOverviewPageLoaderTest extends TestCase
{
    private CollectingEventDispatcher $eventDispatcher;

    /**
     * @var OrderRoute&MockObject
     */
    private OrderRoute $orderRoute;

    private AccountOverviewPageLoader $pageLoader;

    private AbstractTranslator&MockObject $translator;

    private GenericPageLoader&MockObject $genericPageLoader;

    protected function setUp(): void
    {
        $this->eventDispatcher = new CollectingEventDispatcher();
        $this->orderRoute = $this->createMock(OrderRoute::class);
        $this->translator = $this->createMock(AbstractTranslator::class);
        $this->genericPageLoader = $this->createMock(GenericPageLoader::class);

        $this->pageLoader = new AccountOverviewPageLoader(
            $this->genericPageLoader,
            $this->eventDispatcher,
            $this->orderRoute,
            $this->createMock(CustomerRoute::class),
            $this->createMock(NewsletterAccountPageletLoader::class),
            $this->translator
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

        $page = new Page();
        $page->setMetaInformation(new MetaInformation());
        $page->getMetaInformation()?->setMetaTitle('testshop');

        $this->genericPageLoader
            ->expects(static::once())
            ->method('load')
            ->willReturn($page);

        $this->translator
            ->expects(static::once())
            ->method('trans')
            ->willReturn('translated');

        $customer = new CustomerEntity();
        $page = $this->pageLoader->load(new Request(), $this->createMock(SalesChannelContext::class), $customer);

        static::assertEquals($order, $page->getNewestOrder());
        static::assertEquals('translated | testshop', $page->getMetaInformation()?->getMetaTitle());
        static::assertEquals('noindex,follow', $page->getMetaInformation()?->getRobots());

        $events = $this->eventDispatcher->getEvents();
        static::assertCount(2, $events);

        static::assertInstanceOf(OrderRouteRequestEvent::class, $events[0]);
        static::assertInstanceOf(AccountOverviewPageLoadedEvent::class, $events[1]);
    }

    public function testSetStandardMetaDataIfTranslatorIsSet(): void
    {
        $pageLoader = new TestAccountOverviewPageLoader(
            $this->createMock(GenericPageLoader::class),
            $this->eventDispatcher,
            $this->orderRoute,
            $this->createMock(CustomerRoute::class),
            $this->createMock(NewsletterAccountPageletLoader::class),
            $this->translator
        );

        $page = new AccountOverviewPage();

        static::assertNull($page->getMetaInformation());

        $pageLoader->setMetaInformationAccess($page);

        static::assertInstanceOf(MetaInformation::class, $page->getMetaInformation());
    }

    public function testNotSetStandardMetaDataIfTranslatorIsNotSet(): void
    {
        $pageLoader = new TestAccountOverviewPageLoader(
            $this->createMock(GenericPageLoader::class),
            $this->eventDispatcher,
            $this->orderRoute,
            $this->createMock(CustomerRoute::class),
            $this->createMock(NewsletterAccountPageletLoader::class),
            null
        );

        $page = new AccountOverviewPage();

        static::assertNull($page->getMetaInformation());

        $pageLoader->setMetaInformationAccess($page);

        static::assertNull($page->getMetaInformation());
    }
}

/**
 * @internal
 */
class TestAccountOverviewPageLoader extends AccountOverviewPageLoader
{
    public function setMetaInformationAccess(AccountOverviewPage $page): void
    {
        self::setMetaInformation($page);
    }
}
