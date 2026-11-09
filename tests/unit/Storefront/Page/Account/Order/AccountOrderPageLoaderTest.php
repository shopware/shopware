<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Page\Account\Order;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
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
use Shopware\Core\Test\Generator;
use Shopware\Core\Test\Stub\EventDispatcher\CollectingEventDispatcher;
use Shopware\Storefront\Checkout\Order\OrderProductAvailabilityResolver;
use Shopware\Storefront\Event\RouteRequest\OrderRouteRequestEvent;
use Shopware\Storefront\Page\Account\Order\AccountOrderPageLoadedEvent;
use Shopware\Storefront\Page\Account\Order\AccountOrderPageLoader;
use Shopware\Storefront\Page\GenericPageLoader;
use Shopware\Storefront\Page\MetaInformation;
use Shopware\Storefront\Page\Page;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(AccountOrderPageLoader::class)]
class AccountOrderPageLoaderTest extends TestCase
{
    private CollectingEventDispatcher $eventDispatcher;

    private OrderRoute&MockObject $orderRoute;

    private AccountOrderPageLoader $pageLoader;

    private AbstractTranslator&MockObject $translator;

    private GenericPageLoader&MockObject $genericPageLoader;

    protected function setUp(): void
    {
        $this->eventDispatcher = new CollectingEventDispatcher();
        $this->orderRoute = $this->createMock(OrderRoute::class);
        $this->translator = $this->createMock(AbstractTranslator::class);
        $this->genericPageLoader = $this->createMock(GenericPageLoader::class);

        $this->pageLoader = new AccountOrderPageLoader(
            $this->genericPageLoader,
            $this->eventDispatcher,
            $this->orderRoute,
            $this->translator,
            static::createStub(OrderProductAvailabilityResolver::class),
        );
    }

    public function testLoadWithGuestLogin(): void
    {
        $order = new OrderEntity();
        $order->setId(Uuid::randomHex());

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

        $context = Generator::generateSalesChannelContext();

        $this->orderRoute
            ->expects($this->once())
            ->method('load')
            ->with(
                static::callback(static fn (Request $request) => $request->query->get('email') === 'test@example.com' && $request->query->get('zipcode') === '12345' && $request->query->get('login') === true),
                $context,
                static::isInstanceOf(Criteria::class),
            )
            ->willReturn($orderResponse);

        $page = new Page();
        $page->setMetaInformation(new MetaInformation());
        $page->getMetaInformation()?->setMetaTitle('testshop');

        $this->genericPageLoader
            ->expects($this->once())
            ->method('load')
            ->willReturn($page);

        $this->translator
            ->expects($this->once())
            ->method('trans')
            ->willReturn('translated');

        $page = $this->pageLoader->load(new Request(['email' => 'test@example.com', 'zipcode' => '12345']), $context);

        static::assertSame($order, $page->getOrders()->getEntities()->first());
        $metaInformation = $page->getMetaInformation();
        static::assertNotNull($metaInformation);
        static::assertSame('translated | testshop', $metaInformation->getMetaTitle());
        static::assertSame('noindex,follow', $metaInformation->getRobots());

        $events = $this->eventDispatcher->getEvents();
        static::assertCount(2, $events);

        static::assertInstanceOf(OrderRouteRequestEvent::class, $events[0]);
        static::assertInstanceOf(AccountOrderPageLoadedEvent::class, $events[1]);
    }

    #[DataProvider('pageParameterProvider')]
    public function testLoadUsesNonNegativeOffsetForPageParameter(string $pageParameter, int $expectedOffset): void
    {
        $context = Generator::generateSalesChannelContext();

        $this->orderRoute
            ->expects($this->once())
            ->method('load')
            ->with(
                static::isInstanceOf(Request::class),
                $context,
                static::callback(static fn (Criteria $criteria) => $criteria->getOffset() === $expectedOffset),
            )
            ->willReturn(new OrderRouteResponse(
                new EntitySearchResult(
                    OrderDefinition::ENTITY_NAME,
                    0,
                    new OrderCollection(),
                    null,
                    new Criteria(),
                    Context::createDefaultContext()
                )
            ));

        $this->genericPageLoader
            ->expects($this->once())
            ->method('load')
            ->willReturn(new Page());

        $this->translator
            ->expects($this->once())
            ->method('trans')
            ->willReturn('translated');

        $this->pageLoader->load(new Request(['p' => $pageParameter]), $context);
    }

    public static function pageParameterProvider(): \Generator
    {
        yield 'negative page falls back to the first page' => ['-5', 0];
        yield 'zero page falls back to the first page' => ['0', 0];
        yield 'non-numeric page falls back to the first page' => ['foo', 0];
        yield 'valid page is converted to its offset' => ['3', 20];
    }
}
