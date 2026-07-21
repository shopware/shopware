<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Page\Account\Overview;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
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
use Shopware\Core\Framework\Log\Package;
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
#[Package('checkout')]
#[CoversClass(AccountOverviewPageLoader::class)]
class AccountOverviewPageLoaderTest extends TestCase
{
    private CollectingEventDispatcher $eventDispatcher;

    private OrderRoute&Stub $orderRoute;

    private AbstractTranslator&Stub $translator;

    private GenericPageLoader&Stub $genericPageLoader;

    protected function setUp(): void
    {
        $this->eventDispatcher = new CollectingEventDispatcher();
        $this->orderRoute = static::createStub(OrderRoute::class);
        $this->translator = static::createStub(AbstractTranslator::class);
        $this->genericPageLoader = static::createStub(GenericPageLoader::class);
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

        $orderRoute = $this->createMock(OrderRoute::class);
        $orderRoute
            ->expects($this->once())
            ->method('load')
            ->willReturn($orderResponse);

        $page = new Page();
        $page->setMetaInformation(new MetaInformation());
        $page->getMetaInformation()?->setMetaTitle('testshop');

        $genericPageLoader = $this->createMock(GenericPageLoader::class);
        $genericPageLoader
            ->expects($this->once())
            ->method('load')
            ->willReturn($page);

        $translator = $this->createMock(AbstractTranslator::class);
        $translator
            ->expects($this->once())
            ->method('trans')
            ->willReturn('translated');

        $pageLoader = $this->createPageLoader(
            genericPageLoader: $genericPageLoader,
            orderRoute: $orderRoute,
            translator: $translator,
        );

        $customer = new CustomerEntity();
        $page = $pageLoader->load(new Request(), static::createStub(SalesChannelContext::class), $customer);

        static::assertSame($order, $page->getNewestOrder());
        $metaInformation = $page->getMetaInformation();
        static::assertNotNull($metaInformation);
        static::assertSame('translated | testshop', $metaInformation->getMetaTitle());
        static::assertSame('noindex,follow', $metaInformation->getRobots());

        $events = $this->eventDispatcher->getEvents();
        static::assertCount(2, $events);

        static::assertInstanceOf(OrderRouteRequestEvent::class, $events[0]);
        static::assertInstanceOf(AccountOverviewPageLoadedEvent::class, $events[1]);
    }

    public function testSetStandardMetaData(): void
    {
        $pageLoader = new TestAccountOverviewPageLoader(
            static::createStub(GenericPageLoader::class),
            $this->eventDispatcher,
            $this->orderRoute,
            static::createStub(CustomerRoute::class),
            static::createStub(NewsletterAccountPageletLoader::class),
            $this->translator
        );

        $page = new AccountOverviewPage();

        static::assertNull($page->getMetaInformation());

        $pageLoader->setMetaInformationAccess($page);

        static::assertInstanceOf(MetaInformation::class, $page->getMetaInformation());
    }

    private function createPageLoader(
        ?GenericPageLoader $genericPageLoader = null,
        ?OrderRoute $orderRoute = null,
        ?AbstractTranslator $translator = null,
    ): AccountOverviewPageLoader {
        return new AccountOverviewPageLoader(
            $genericPageLoader ?? $this->genericPageLoader,
            $this->eventDispatcher,
            $orderRoute ?? $this->orderRoute,
            static::createStub(CustomerRoute::class),
            static::createStub(NewsletterAccountPageletLoader::class),
            $translator ?? $this->translator
        );
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
