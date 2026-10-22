<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Framework\Routing;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\CartException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\KernelListenerPriorities;
use Shopware\Core\Framework\Routing\RoutingException;
use Shopware\Core\PlatformRequest;
use Shopware\Core\SalesChannelRequest;
use Shopware\Storefront\Event\MaintenanceRedirectEvent;
use Shopware\Storefront\Framework\Routing\MaintenanceModeResolver;
use Shopware\Storefront\Framework\Routing\StorefrontRouteScope;
use Shopware\Storefront\Framework\Routing\StorefrontSubscriber;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\RouterInterface;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(StorefrontSubscriber::class)]
class StorefrontSubscriberTest extends TestCase
{
    public function testHasEvents(): void
    {
        $expected = [
            KernelEvents::REQUEST => [
                ['maintenanceResolver'],
            ],
            KernelEvents::EXCEPTION => [
                ['customerNotLoggedInHandler'],
                ['maintenanceResolver'],
            ],
            KernelEvents::CONTROLLER => [
                ['preventPageLoadingFromXmlHttpRequest', KernelListenerPriorities::KERNEL_CONTROLLER_EVENT_SCOPE_VALIDATE],
            ],
        ];

        static::assertSame($expected, StorefrontSubscriber::getSubscribedEvents());
    }

    public function testMaintenanceRedirect(): void
    {
        $maintenanceModeResolver = static::createStub(MaintenanceModeResolver::class);
        $maintenanceModeResolver
            ->method('shouldRedirect')
            ->willReturn(true);

        $router = static::createStub(RouterInterface::class);
        $router->method('generate')->willReturn('/maintenance');

        $event = new RequestEvent(
            static::createStub(HttpKernelInterface::class),
            new Request(),
            HttpKernelInterface::MAIN_REQUEST
        );

        $eventDispatcher = new EventDispatcher();
        $eventIsThrown = false;
        $eventDispatcher->addListener(
            MaintenanceRedirectEvent::class,
            static function () use (&$eventIsThrown): void {
                $eventIsThrown = true;
            }
        );

        (new StorefrontSubscriber($router, $maintenanceModeResolver, $eventDispatcher))->maintenanceResolver($event);

        $response = $event->getResponse();
        static::assertInstanceOf(RedirectResponse::class, $response);
        static::assertSame('/maintenance', $response->getTargetUrl());
        static::assertTrue($eventIsThrown);
    }

    public function testMaintenanceParametersRedirect(): void
    {
        $maintenanceModeResolver = static::createStub(MaintenanceModeResolver::class);
        $maintenanceModeResolver
            ->method('shouldRedirect')
            ->willReturn(true);

        $router = static::createStub(RouterInterface::class);
        $router->method('generate')->willReturn('/maintenance?foo=bar');

        $request = new Request(
            query: [
                'bar' => 'foo',
            ],
            attributes: [
                '_route' => 'product_page',
                '_route_params' => [
                    'foo' => 'bar',
                    'productId' => 123,
                    PlatformRequest::ATTRIBUTE_INTERNAL_ROUTE_PARAMS[0] => true,
                    PlatformRequest::ATTRIBUTE_INTERNAL_ROUTE_PARAMS[1] => true,
                ],
            ],
        );

        $event = new RequestEvent(
            static::createStub(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST
        );

        $eventDispatcher = new EventDispatcher();
        $eventIsThrown = false;
        $eventDispatcher->addListener(
            MaintenanceRedirectEvent::class,
            static function (MaintenanceRedirectEvent $event) use (&$eventIsThrown): void {
                $parameters = $event->getParameters();
                static::assertEquals('product_page', $parameters['redirectTo']);
                static::assertEquals('{"bar":"foo","foo":"bar","productId":123}', $parameters['redirectParameters']);

                $eventIsThrown = true;
            }
        );

        (new StorefrontSubscriber($router, $maintenanceModeResolver, $eventDispatcher))->maintenanceResolver($event);

        static::assertTrue($eventIsThrown);
    }

    #[DataProvider('customerNotLoggedInHandlerProvider')]
    public function testCustomerNotLoggedInHandler(\Throwable $exception, bool $isXmlHttpRequest, bool $expectRedirect): void
    {
        $router = $this->createMock(RouterInterface::class);
        $router->expects($expectRedirect ? $this->once() : $this->never())
            ->method('generate')
            ->with('frontend.account.login.page')
            ->willReturn('/login');

        $server = $isXmlHttpRequest ? ['HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest'] : [];

        $event = new ExceptionEvent(
            static::createStub(HttpKernelInterface::class),
            new Request(
                attributes: [SalesChannelRequest::ATTRIBUTE_IS_SALES_CHANNEL_REQUEST => true],
                server: $server,
            ),
            HttpKernelInterface::MAIN_REQUEST,
            $exception
        );

        (new StorefrontSubscriber(
            $router,
            static::createStub(MaintenanceModeResolver::class),
            new EventDispatcher(),
        ))->customerNotLoggedInHandler($event);

        if ($expectRedirect) {
            static::assertInstanceOf(RedirectResponse::class, $event->getResponse());

            return;
        }

        static::assertFalse($event->hasResponse());
    }

    public function testRedirectCustomerNonStorefrontRequest(): void
    {
        $event = new ExceptionEvent(
            static::createStub(HttpKernelInterface::class),
            new Request(),
            HttpKernelInterface::MAIN_REQUEST,
            new \RuntimeException('test')
        );

        (new StorefrontSubscriber(
            static::createStub(RouterInterface::class),
            static::createStub(MaintenanceModeResolver::class),
            new EventDispatcher(),
        ))->customerNotLoggedInHandler($event);

        static::assertFalse($event->hasResponse());
    }

    public static function customerNotLoggedInHandlerProvider(): \Generator
    {
        yield 'routing exception redirects regular request' => [
            'exception' => RoutingException::customerNotLoggedIn(),
            'isXmlHttpRequest' => false,
            'expectRedirect' => true,
        ];

        yield 'routing exception does not redirect XHR request' => [
            'exception' => RoutingException::customerNotLoggedIn(),
            'isXmlHttpRequest' => true,
            'expectRedirect' => false,
        ];

        yield 'cart exception redirects regular request' => [
            'exception' => CartException::customerNotLoggedIn(),
            'isXmlHttpRequest' => false,
            'expectRedirect' => true,
        ];

        yield 'cart exception does not redirect XHR request' => [
            'exception' => CartException::customerNotLoggedIn(),
            'isXmlHttpRequest' => true,
            'expectRedirect' => false,
        ];

        yield 'unrelated exception does not redirect' => [
            'exception' => new \RuntimeException('test'),
            'isXmlHttpRequest' => false,
            'expectRedirect' => false,
        ];
    }

    #[DataProvider('dataProviderXMLHttpRequest')]
    public function testNonXmlHttpRequestPassesThrough(Request $request, bool $expected): void
    {
        $event = new ControllerEvent(
            static::createStub(HttpKernelInterface::class),
            static function (): void {},
            $request,
            HttpKernelInterface::MAIN_REQUEST
        );

        if ($expected) {
            $route = $request->attributes->get('_route');
            $url = $request->getUri();
            $referer = $request->headers->get('referer');

            $this->expectExceptionObject(RoutingException::accessDeniedForXmlHttpRequest($route, $url, $referer));
        } else {
            static::assertTrue($event->isMainRequest());
        }

        (new StorefrontSubscriber(
            static::createStub(RouterInterface::class),
            static::createStub(MaintenanceModeResolver::class),
            new EventDispatcher(),
        ))->preventPageLoadingFromXmlHttpRequest($event);
    }

    public static function dataProviderXMLHttpRequest(): \Generator
    {
        yield 'not an XMLHttpRequest' => [
            'request' => new Request(),
            'expected' => false,
        ];

        yield 'XMLHttpRequest, but not a storefront request' => [
            'request' => new Request(server: ['HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest']),
            'expected' => false,
        ];

        yield 'XMLHttpRequest, but a storefront request and not allowed' => [
            'request' => new Request(
                attributes: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StorefrontRouteScope::ID]],
                server: ['HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest']
            ),
            'expected' => true,
        ];

        yield 'XMLHttpRequest, but a storefront request and allowed' => [
            'request' => new Request(
                attributes: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StorefrontRouteScope::ID], 'XmlHttpRequest' => true],
                server: ['HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest']
            ),
            'expected' => false,
        ];
    }
}
