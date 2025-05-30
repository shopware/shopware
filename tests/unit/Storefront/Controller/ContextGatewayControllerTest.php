<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Controller;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Framework\Gateway\Context\SalesChannel\AbstractContextGatewayRoute;
use Shopware\Core\Framework\Gateway\GatewayException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestSessionStorage;
use Shopware\Core\System\SalesChannel\ContextTokenResponse;
use Shopware\Core\Test\Generator;
use Shopware\Storefront\Controller\ContextGatewayController;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;
use Symfony\Component\HttpFoundation\Session\Session;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ContextGatewayController::class)]
class ContextGatewayControllerTest extends TestCase
{
    public function testGateway(): void
    {
        $context = Generator::generateSalesChannelContext(token: 'hatoken');
        $cart = new Cart('hatoken');
        $request = new Request(request: ['foo' => 'bar', 'bat' => 'baz']);
        $expectedResponse = new ContextTokenResponse('newHatoken');

        $cartService = $this->createMock(CartService::class);
        $cartService
            ->expects($this->once())
            ->method('getCart')
            ->with('hatoken', $context)
            ->willReturn($cart);

        $route = $this->createMock(AbstractContextGatewayRoute::class);
        $route
            ->expects($this->once())
            ->method('load')
            ->with($request, $cart, $context)
            ->willReturn($expectedResponse);

        $container = $this->createStubContainerWithFlashBag();

        $controller = new ContextGatewayController($route, $cartService);
        $controller->setContainer($container);

        $newResponse = $controller->gateway($request, $context);

        static::assertInstanceOf(ContextTokenResponse::class, $newResponse);
        static::assertSame('newHatoken', $newResponse->getToken());
    }

    public function testGatewayWithGenericException(): void
    {
        $context = Generator::generateSalesChannelContext(token: 'hatoken');
        $cart = new Cart('hatoken');
        $request = new Request(request: ['foo' => 'bar', 'bat' => 'baz']);

        $cartService = $this->createMock(CartService::class);
        $cartService
            ->expects($this->once())
            ->method('getCart')
            ->with('hatoken', $context)
            ->willReturn($cart);

        $route = $this->createMock(AbstractContextGatewayRoute::class);
        $route
            ->expects($this->once())
            ->method('load')
            ->with($request, $cart, $context)
            ->willThrowException(GatewayException::emptyAppResponse('FOO'));

        $container = $this->createStubContainerWithFlashBag();

        $controller = new ContextGatewayController($route, $cartService);
        $controller->setContainer($container);

        $newResponse = $controller->gateway($request, $context);

        static::assertInstanceOf(JsonResponse::class, $newResponse);
        static::assertSame(Response::HTTP_BAD_REQUEST, $newResponse->getStatusCode());

        $session = $container->get('request_stack')->getSession();

        static::assertInstanceOf(FlashBagAwareSessionInterface::class, $session);

        $flashBag = $session->getFlashBag();
        $errors = $flashBag->get('danger');

        static::assertCount(0, $errors);
    }

    public function testGatewayWithCustomerException(): void
    {
        $context = Generator::generateSalesChannelContext(token: 'hatoken');
        $cart = new Cart('hatoken');
        $request = new Request(request: ['foo' => 'bar', 'bat' => 'baz']);

        $cartService = $this->createMock(CartService::class);
        $cartService
            ->expects($this->once())
            ->method('getCart')
            ->with('hatoken', $context)
            ->willReturn($cart);

        $route = $this->createMock(AbstractContextGatewayRoute::class);
        $route
            ->expects($this->once())
            ->method('load')
            ->with($request, $cart, $context)
            ->willThrowException(GatewayException::customerMessage('FOO'));

        $container = $this->createStubContainerWithFlashBag();

        $controller = new ContextGatewayController($route, $cartService);
        $controller->setContainer($container);

        $newResponse = $controller->gateway($request, $context);

        static::assertInstanceOf(JsonResponse::class, $newResponse);
        static::assertSame(Response::HTTP_BAD_REQUEST, $newResponse->getStatusCode());

        $session = $container->get('request_stack')->getSession();

        static::assertInstanceOf(FlashBagAwareSessionInterface::class, $session);

        $flashBag = $session->getFlashBag();
        $errors = $flashBag->get('danger');

        static::assertCount(1, $errors);

        $error = $errors[0];

        static::assertSame('FOO', $error);
    }

    private function createStubContainerWithFlashBag(): ContainerInterface
    {
        $session = new Session(new TestSessionStorage());
        $request = new Request();
        $request->setSession($session);

        $requestStack = new RequestStack([$request]);

        $container = $this->createMock(ContainerInterface::class);
        $container
            ->method('get')
            ->with('request_stack')
            ->willReturn($requestStack);

        return $container;
    }
}
