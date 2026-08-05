<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart\Order\Api;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\AbstractCartPersister;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartException;
use Shopware\Core\Checkout\Cart\Order\Api\OrderConverterController;
use Shopware\Core\Checkout\Cart\Order\OrderConverter;
use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Acl\AclAnnotationValidator;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Api\Exception\MissingPrivilegeException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Symfony\Bundle\FrameworkBundle\Routing\AttributeRouteControllerLoader;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Routing\Route;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(OrderConverterController::class)]
class OrderConverterControllerTest extends TestCase
{
    public function testConvertToCartRouteDeclaresOrderUpdatePrivilege(): void
    {
        static::assertSame(['order:update'], $this->loadConvertToCartRoute()->getDefault(PlatformRequest::ATTRIBUTE_ACL));
    }

    public function testConvertToCartIsRejectedWithoutOrderUpdate(): void
    {
        $exception = $this->validate($this->loadConvertToCartRoute(), ['order:read']);

        static::assertInstanceOf(MissingPrivilegeException::class, $exception, 'The convert-to-cart route is not protected');
        static::assertSame(['order:update'], json_decode($exception->getMessage(), true)['missingPrivileges']);
    }

    public function testConvertToCartIsAcceptedWithOrderUpdate(): void
    {
        static::assertNull(
            $this->validate($this->loadConvertToCartRoute(), ['order:update']),
            'The convert-to-cart route rejected a user holding order:update'
        );
    }

    public function testOrderNotFoundException(): void
    {
        $orderId = Uuid::randomHex();
        $this->expectExceptionObject(CartException::orderNotFound($orderId));

        $converter = static::createStub(OrderConverter::class);
        $persister = static::createStub(AbstractCartPersister::class);

        /** @var StaticEntityRepository<OrderCollection> */
        $orderRepository = new StaticEntityRepository([new OrderCollection([])]);

        $controller = new OrderConverterController($converter, $persister, $orderRepository);
        $controller->convertToCart($orderId, Context::createDefaultContext());
    }

    public function testOrderConvertToCart(): void
    {
        $converter = $this->createMock(OrderConverter::class);
        $persister = $this->createMock(AbstractCartPersister::class);

        $orderId = Uuid::randomHex();
        $order = new OrderEntity();
        $order->setId($orderId);

        $cart = new Cart('test');
        $converter
            ->expects($this->once())
            ->method('convertToCart')
            ->with($order)
            ->willReturn($cart);
        $converter
            ->expects($this->once())
            ->method('assembleSalesChannelContext')
            ->willReturn(static::createStub(SalesChannelContext::class));

        $persister
            ->expects($this->once())
            ->method('save')
            ->with($cart);

        /** @var StaticEntityRepository<OrderCollection> */
        $orderRepository = new StaticEntityRepository([new OrderCollection([$order])]);

        $controller = new OrderConverterController($converter, $persister, $orderRepository);
        $response = $controller->convertToCart($orderId, Context::createDefaultContext());
        $data = json_decode((string) $response->getContent(), true);

        static::assertSame($cart->getToken(), $data['token']);
    }

    private function loadConvertToCartRoute(): Route
    {
        $route = (new AttributeRouteControllerLoader())->load(OrderConverterController::class)->get('api.action.order.convert-to-cart');

        static::assertNotNull($route, \sprintf('Route "api.action.order.convert-to-cart" is not defined on %s', OrderConverterController::class));

        return $route;
    }

    /**
     * Runs the privileges of a real route through the real validator, without booting the kernel.
     *
     * @param list<string> $permissions
     */
    private function validate(Route $route, array $permissions): ?\Throwable
    {
        $source = new AdminApiSource(null, null);
        $source->setPermissions($permissions);

        $request = new Request();
        $request->attributes->set(PlatformRequest::ATTRIBUTE_ACL, $route->getDefault(PlatformRequest::ATTRIBUTE_ACL));
        $request->attributes->set(
            PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT,
            new Context($source, [], Defaults::CURRENCY, [Defaults::LANGUAGE_SYSTEM])
        );

        $event = new ControllerEvent(
            static::createStub(HttpKernelInterface::class),
            static fn () => null,
            $request,
            HttpKernelInterface::MAIN_REQUEST
        );

        try {
            (new AclAnnotationValidator(static::createStub(Connection::class)))->validate($event);
        } catch (\Throwable $exception) {
            return $exception;
        }

        return null;
    }
}
