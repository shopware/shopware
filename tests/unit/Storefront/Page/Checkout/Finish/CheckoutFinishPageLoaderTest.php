<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Page\Checkout\Finish;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\CartException;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Order\OrderException;
use Shopware\Core\Checkout\Order\SalesChannel\OrderRoute;
use Shopware\Core\Checkout\Order\SalesChannel\OrderRouteResponse;
use Shopware\Core\Framework\Adapter\Translation\AbstractTranslator;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\CashRoundingConfig;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Routing\RoutingException;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\Generator;
use Shopware\Core\Test\Stub\SystemConfigService\StaticSystemConfigService;
use Shopware\Storefront\Page\Checkout\Finish\CheckoutFinishPage;
use Shopware\Storefront\Page\Checkout\Finish\CheckoutFinishPageLoader;
use Shopware\Storefront\Page\GenericPageLoader;
use Shopware\Storefront\Page\MetaInformation;
use Shopware\Storefront\Page\Page;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(CheckoutFinishPageLoader::class)]
class CheckoutFinishPageLoaderTest extends TestCase
{
    public function testRobotsMetaNotSetIfGiven(): void
    {
        $orderId = Uuid::randomHex();

        $page = new CheckoutFinishPage();

        $pageLoader = $this->createMock(GenericPageLoader::class);
        $pageLoader->method('load')
            ->willReturn($page);

        $request = new Request([], [], [
            'orderId' => $orderId,
            'changedPayment' => false,
            'paymentFailed' => false,
        ]);

        $page = $this->createLoader($pageLoader, $this->getOrderRouteWithValidOrder($orderId))->load(
            $request,
            Generator::generateSalesChannelContext(),
        );

        static::assertNull($page->getMetaInformation());
    }

    public function testRobotsMetaSetIfGiven(): void
    {
        $orderId = Uuid::randomHex();

        $page = new CheckoutFinishPage();
        $page->setMetaInformation(new MetaInformation());

        $pageLoader = $this->createMock(GenericPageLoader::class);
        $pageLoader->method('load')
            ->willReturn($page);

        $request = new Request([], [], [
            'orderId' => $orderId,
            'changedPayment' => false,
            'paymentFailed' => false,
        ]);

        $page = $this->createLoader($pageLoader, $this->getOrderRouteWithValidOrder($orderId))->load(
            $request,
            Generator::generateSalesChannelContext(),
        );
        static::assertNotNull($page->getMetaInformation());
        static::assertSame('noindex,follow', $page->getMetaInformation()->getRobots());
    }

    public function testCheckoutFinishPageReturned(): void
    {
        $orderId = Uuid::randomHex();

        $pageLoader = $this->createMock(GenericPageLoader::class);
        $pageLoader->method('load')
            ->willReturn(new Page());

        $request = new Request([], [], [
            'orderId' => $orderId,
            'changedPayment' => false,
            'paymentFailed' => false,
        ]);

        $this->createLoader($pageLoader, $this->getOrderRouteWithValidOrder($orderId))->load(
            $request,
            Generator::generateSalesChannelContext(),
        );
    }

    #[DataProvider('provideGuestLogoutConstellations')]
    public function testLogoutCustomerSetCorrectly(bool $isGuest, bool $guestLogoutSetting, bool $expected): void
    {
        $orderId = Uuid::randomHex();

        $pageLoader = $this->createMock(GenericPageLoader::class);
        $pageLoader->method('load')
            ->willReturn(new Page());

        $request = new Request([], [], [
            'orderId' => $orderId,
            'changedPayment' => false,
            'paymentFailed' => false,
        ]);

        $context = Generator::generateSalesChannelContext();
        $context->getCustomer()?->setGuest($isGuest);
        $config = [
            $context->getSalesChannelId() => ['core.cart.logoutGuestAfterCheckout' => $guestLogoutSetting],
        ];

        $page = $this->createLoader($pageLoader, $this->getOrderRouteWithValidOrder($orderId), $config)->load($request, $context);

        static::assertSame($expected, $page->isLogoutCustomer());
    }

    public function testRoundingIsSetInContext(): void
    {
        $orderId = Uuid::randomHex();
        $itemRounding = new CashRoundingConfig(2, 2.0, false);
        $totalRounding = new CashRoundingConfig(4, 4.0, false);

        $pageLoader = $this->createMock(GenericPageLoader::class);
        $pageLoader->method('load')
            ->willReturn(new Page());

        $request = new Request([], [], [
            'orderId' => $orderId,
            'changedPayment' => false,
            'paymentFailed' => false,
        ]);

        $salesChannelContext = Generator::generateSalesChannelContext();
        $this->createLoader($pageLoader, $this->getOrderRouteWithValidOrder($orderId, $itemRounding, $totalRounding))->load(
            $request,
            $salesChannelContext,
        );

        static::assertSame($salesChannelContext->getItemRounding(), $itemRounding);
        static::assertSame($salesChannelContext->getTotalRounding(), $totalRounding);
    }

    public function testNoCustomerLoggedInException(): void
    {
        $pageLoader = $this->createMock(GenericPageLoader::class);
        $pageLoader->method('load')
            ->willReturn(new Page());

        $this->expectException(CartException::class);

        $this->createLoader($pageLoader, $this->createMock(OrderRoute::class))->load(
            new Request(),
            $this->createMock(SalesChannelContext::class),
        );
    }

    public function testMissingOrderIdException(): void
    {
        $pageLoader = $this->createMock(GenericPageLoader::class);
        $pageLoader->method('load')
            ->willReturn(new Page());

        $this->expectException(RoutingException::class);

        $this->createLoader($pageLoader, $this->createMock(OrderRoute::class))->load(
            new Request(),
            Generator::generateSalesChannelContext(),
        );
    }

    public function testOrderNotFoundException(): void
    {
        $orderId = Uuid::randomHex();

        $pageLoader = $this->createMock(GenericPageLoader::class);
        $pageLoader->method('load')
            ->willReturn(new Page());

        $request = new Request([], [], [
            'orderId' => 'invalid-order-id',
        ]);

        try {
            $this->createLoader($pageLoader, $this->getOrderRouteWithValidOrder($orderId))->load(
                $request,
                Generator::generateSalesChannelContext(),
            );
        } catch (OrderException) {
        } catch (\Exception) {
            static::fail('Not an expected Exception');
        }
    }

    public static function provideGuestLogoutConstellations(): \Generator
    {
        yield 'Guest customer, setting off' => [true, false, false];
        yield 'Logged in customer, setting on' => [false, true, false];
        yield 'Guest customer, setting on' => [true, true, true];
    }

    /**
     * @param array<string, mixed> $systemConfig
     */
    private function createLoader(GenericPageLoader $pageLoader, OrderRoute $getOrderRouteWithValidOrder, array $systemConfig = []): CheckoutFinishPageLoader
    {
        return new CheckoutFinishPageLoader(
            $this->createMock(EventDispatcher::class),
            $pageLoader,
            $getOrderRouteWithValidOrder,
            $this->createMock(AbstractTranslator::class),
            new StaticSystemConfigService($systemConfig),
        );
    }

    private function getOrderRouteWithValidOrder(string $orderId, ?CashRoundingConfig $itemRounding = null, ?CashRoundingConfig $totalRounding = null): OrderRoute
    {
        $order = new OrderEntity();
        $order->setId($orderId);

        if ($itemRounding instanceof CashRoundingConfig) {
            $order->setItemRounding($itemRounding);
        }

        if ($totalRounding instanceof CashRoundingConfig) {
            $order->setTotalRounding($totalRounding);
        }

        $searchResult = new EntitySearchResult(
            OrderDefinition::ENTITY_NAME,
            1,
            new EntityCollection([$order]),
            null,
            new Criteria(),
            Context::createDefaultContext(),
        );

        $orderRouteResponse = $this->createMock(OrderRouteResponse::class);
        $orderRouteResponse->expects($this->once())
            ->method('getOrders')
            ->willReturn($searchResult);

        $orderRoute = $this->createMock(OrderRoute::class);
        $orderRoute->expects($this->once())
            ->method('load')
            ->willReturn($orderRouteResponse);

        return $orderRoute;
    }
}
