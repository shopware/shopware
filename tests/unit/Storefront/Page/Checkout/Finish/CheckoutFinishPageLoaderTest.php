<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Page\Checkout\Finish;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\CartException;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Order\OrderException;
use Shopware\Core\Checkout\Order\SalesChannel\OrderRoute;
use Shopware\Core\Checkout\Order\SalesChannel\OrderRouteResponse;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\CashRoundingConfig;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Routing\RoutingException;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
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

        $checkoutFinishPageLoader = new CheckoutFinishPageLoader(
            $this->createMock(EventDispatcher::class),
            $pageLoader,
            $this->getOrderRouteWithValidOrder($orderId),
        );

        $request = new Request([], [], [
            'orderId' => $orderId,
            'changedPayment' => false,
            'paymentFailed' => false,
        ]);

        $page = $checkoutFinishPageLoader->load(
            $request,
            $this->getContextWithDummyCustomer(),
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

        $checkoutFinishPageLoader = new CheckoutFinishPageLoader(
            $this->createMock(EventDispatcher::class),
            $pageLoader,
            $this->getOrderRouteWithValidOrder($orderId),
        );

        $request = new Request([], [], [
            'orderId' => $orderId,
            'changedPayment' => false,
            'paymentFailed' => false,
        ]);

        $page = $checkoutFinishPageLoader->load(
            $request,
            $this->getContextWithDummyCustomer(),
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

        $checkoutFinishPageLoader = new CheckoutFinishPageLoader(
            $this->createMock(EventDispatcher::class),
            $pageLoader,
            $this->getOrderRouteWithValidOrder($orderId),
        );

        $request = new Request([], [], [
            'orderId' => $orderId,
            'changedPayment' => false,
            'paymentFailed' => false,
        ]);

        $checkoutFinishPageLoader->load(
            $request,
            $this->getContextWithDummyCustomer(),
        );
    }

    public function testItemRoundingIsSetInContext(): void
    {
        $orderId = Uuid::randomHex();
        $itemRounding = new CashRoundingConfig(2, 2.0, false);

        $pageLoader = $this->createMock(GenericPageLoader::class);
        $pageLoader->method('load')
            ->willReturn(new Page());

        $checkoutFinishPageLoader = new CheckoutFinishPageLoader(
            $this->createMock(EventDispatcher::class),
            $pageLoader,
            $this->getOrderRouteWithValidOrder($orderId, $itemRounding),
        );

        $request = new Request([], [], [
            'orderId' => $orderId,
            'changedPayment' => false,
            'paymentFailed' => false,
        ]);

        $salesChannelContext = $this->getContextWithDummyCustomer();
        $salesChannelContext->expects(static::once())
            ->method('setItemRounding')
            ->willReturnCallback(function (CashRoundingConfig $givenItemRounding) use ($itemRounding): void {
                static::assertSame($itemRounding, $givenItemRounding);
            });

        $checkoutFinishPageLoader->load(
            $request,
            $salesChannelContext,
        );
    }

    public function testTotalRoundingIsSetInContext(): void
    {
        $orderId = Uuid::randomHex();
        $totalRounding = new CashRoundingConfig(2, 2.0, false);

        $pageLoader = $this->createMock(GenericPageLoader::class);
        $pageLoader->method('load')
            ->willReturn(new Page());

        $checkoutFinishPageLoader = new CheckoutFinishPageLoader(
            $this->createMock(EventDispatcher::class),
            $pageLoader,
            $this->getOrderRouteWithValidOrder($orderId, null, $totalRounding),
        );

        $request = new Request([], [], [
            'orderId' => $orderId,
            'changedPayment' => false,
            'paymentFailed' => false,
        ]);

        $salesChannelContext = $this->getContextWithDummyCustomer();
        $salesChannelContext->expects(static::once())
            ->method('setTotalRounding')
            ->willReturnCallback(function (CashRoundingConfig $givenItemRounding) use ($totalRounding): void {
                static::assertSame($totalRounding, $givenItemRounding);
            });

        $checkoutFinishPageLoader->load(
            $request,
            $salesChannelContext,
        );
    }

    public function testNoCustomerLoggedInException(): void
    {
        $pageLoader = $this->createMock(GenericPageLoader::class);
        $pageLoader->method('load')
            ->willReturn(new Page());

        $checkoutFinishPageLoader = new CheckoutFinishPageLoader(
            $this->createMock(EventDispatcher::class),
            $pageLoader,
            $this->createMock(OrderRoute::class),
        );

        static::expectException(CartException::class);

        $checkoutFinishPageLoader->load(
            new Request(),
            $this->createMock(SalesChannelContext::class),
        );
    }

    public function testMissingOrderIdException(): void
    {
        $pageLoader = $this->createMock(GenericPageLoader::class);
        $pageLoader->method('load')
            ->willReturn(new Page());

        $checkoutFinishPageLoader = new CheckoutFinishPageLoader(
            $this->createMock(EventDispatcher::class),
            $pageLoader,
            $this->createMock(OrderRoute::class),
        );

        static::expectException(RoutingException::class);

        $checkoutFinishPageLoader->load(
            new Request(),
            $this->getContextWithDummyCustomer(),
        );
    }

    public function testOrderNotFoundException(): void
    {
        $orderId = Uuid::randomHex();

        $pageLoader = $this->createMock(GenericPageLoader::class);
        $pageLoader->method('load')
            ->willReturn(new Page());

        $checkoutFinishPageLoader = new CheckoutFinishPageLoader(
            $this->createMock(EventDispatcher::class),
            $pageLoader,
            $this->getOrderRouteWithValidOrder($orderId),
        );

        $request = new Request([], [], [
            'orderId' => 'invalid-order-id',
        ]);

        try {
            $checkoutFinishPageLoader->load(
                $request,
                $this->getContextWithDummyCustomer(),
            );
        } catch (OrderException) {
        } catch (\Exception) {
            static::fail('Not an expected Exception');
        }
    }

    /**
     * @return SalesChannelContext&MockObject
     */
    private function getContextWithDummyCustomer(): SalesChannelContext
    {
        $address = (new CustomerAddressEntity())->assign(['id' => Uuid::randomHex()]);

        $customer = new CustomerEntity();
        $customer->assign([
            'activeBillingAddress' => $address,
            'activeShippingAddress' => $address,
            'id' => Uuid::randomHex(),
        ]);

        $context = $this->createMock(SalesChannelContext::class);
        $context->method('getCustomer')
            ->willReturn($customer);

        return $context;
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
        $orderRouteResponse->expects(static::once())
            ->method('getOrders')
            ->willReturn($searchResult);

        $orderRoute = $this->createMock(OrderRoute::class);
        $orderRoute->expects(static::once())
            ->method('load')
            ->willReturn($orderRouteResponse);

        return $orderRoute;
    }
}
