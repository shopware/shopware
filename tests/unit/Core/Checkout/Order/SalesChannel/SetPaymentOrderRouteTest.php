<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Order\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartRuleLoader;
use Shopware\Core\Checkout\Cart\Order\OrderConverter;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Order\OrderException;
use Shopware\Core\Checkout\Order\SalesChannel\OrderService;
use Shopware\Core\Checkout\Order\SalesChannel\SetPaymentOrderRoute;
use Shopware\Core\Checkout\Payment\PaymentMethodCollection;
use Shopware\Core\Checkout\Payment\PaymentMethodDefinition;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Checkout\Payment\SalesChannel\AbstractPaymentMethodRoute;
use Shopware\Core\Checkout\Payment\SalesChannel\PaymentMethodRouteResponse;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\StateMachine\Loader\InitialStateIdLoader;
use Shopware\Core\Test\Generator;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(SetPaymentOrderRoute::class)]
class SetPaymentOrderRouteTest extends TestCase
{
    public function testSetPaymentProvidesOrderCartToPaymentAvailability(): void
    {
        $orderId = Uuid::randomHex();
        $paymentMethodId = Uuid::randomHex();

        $order = new OrderEntity();
        $order->setId($orderId);

        $salesChannelContext = Generator::generateSalesChannelContext(token: 'storefront-token');
        $orderContext = Generator::generateSalesChannelContext(token: 'restored-order-token');

        $orderConverter = $this->createMock(OrderConverter::class);
        $orderConverter
            ->expects($this->once())
            ->method('assembleSalesChannelContext')
            ->with(
                static::identicalTo($order),
                static::identicalTo($salesChannelContext->getContext()),
                [SalesChannelContextService::PAYMENT_METHOD_ID => $paymentMethodId]
            )
            ->willReturn($orderContext);

        $convertedCart = new Cart('converted-order-token');
        $orderConverter
            ->expects($this->once())
            ->method('convertToCart')
            ->with(static::identicalTo($order), static::identicalTo($orderContext->getContext()))
            ->willReturn($convertedCart);

        $cartService = $this->createMock(CartService::class);
        $cartService
            ->expects($this->once())
            ->method('setCart')
            ->with(static::callback(static function (Cart $cart) use ($orderContext): bool {
                static::assertSame($orderContext->getToken(), $cart->getToken());

                return true;
            }));

        $paymentRoute = $this->createMock(AbstractPaymentMethodRoute::class);
        $paymentRoute
            ->expects($this->once())
            ->method('load')
            ->with(
                static::callback(static function (Request $request) use ($orderId): bool {
                    static::assertSame('1', $request->query->get('onlyAvailable'));
                    static::assertSame($orderId, $request->attributes->get('orderId'));

                    return true;
                }),
                static::identicalTo($orderContext),
                static::isInstanceOf(Criteria::class)
            )
            ->willReturn($this->createPaymentMethodRouteResponse(new PaymentMethodCollection()));

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher
            ->method('dispatch')
            ->willReturnArgument(0);

        $route = new SetPaymentOrderRoute(
            $this->createMock(OrderService::class),
            new StaticEntityRepository([new OrderCollection([$order])]),
            $paymentRoute,
            $orderConverter,
            $this->createMock(CartRuleLoader::class),
            $cartService,
            $eventDispatcher,
            $this->createMock(InitialStateIdLoader::class)
        );

        $this->expectException(OrderException::class);

        $route->setPayment(new Request(request: [
            'orderId' => $orderId,
            'paymentMethodId' => $paymentMethodId,
        ]), $salesChannelContext);
    }

    public function testPaymentMethodNotAfterOrderEnabled(): void
    {
        $orderId = Uuid::randomHex();
        $paymentMethodId = Uuid::randomHex();

        $order = new OrderEntity();
        $order->setId($orderId);

        $paymentMethod = new PaymentMethodEntity();
        $paymentMethod->setId($paymentMethodId);
        $paymentMethod->setAfterOrderEnabled(false);

        $salesChannelContext = Generator::generateSalesChannelContext(token: 'storefront-token');
        $orderContext = Generator::generateSalesChannelContext(token: 'restored-order-token');

        $orderConverter = $this->createMock(OrderConverter::class);
        $orderConverter
            ->method('assembleSalesChannelContext')
            ->willReturn($orderContext);
        $orderConverter
            ->method('convertToCart')
            ->willReturn(new Cart('converted-order-token'));

        $paymentRoute = $this->createMock(AbstractPaymentMethodRoute::class);
        $paymentRoute
            ->method('load')
            ->willReturn($this->createPaymentMethodRouteResponse(new PaymentMethodCollection([$paymentMethod])));

        $route = new SetPaymentOrderRoute(
            $this->createMock(OrderService::class),
            new StaticEntityRepository([new OrderCollection([$order])]),
            $paymentRoute,
            $orderConverter,
            $this->createMock(CartRuleLoader::class),
            $this->createMock(CartService::class),
            $this->createMock(EventDispatcherInterface::class),
            $this->createMock(InitialStateIdLoader::class)
        );

        // A method that is available but has "Allow payment change after checkout" disabled must be rejected.
        $this->expectExceptionObject(OrderException::paymentMethodNotChangeable());

        $route->setPayment(new Request(request: [
            'orderId' => $orderId,
            'paymentMethodId' => $paymentMethodId,
        ]), $salesChannelContext);
    }

    private function createPaymentMethodRouteResponse(PaymentMethodCollection $paymentMethods): PaymentMethodRouteResponse
    {
        return new PaymentMethodRouteResponse(
            new EntitySearchResult(
                PaymentMethodDefinition::ENTITY_NAME,
                $paymentMethods->count(),
                $paymentMethods,
                null,
                new Criteria(),
                Generator::generateSalesChannelContext()->getContext()
            )
        );
    }
}
