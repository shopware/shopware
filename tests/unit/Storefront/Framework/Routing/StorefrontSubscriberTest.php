<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Framework\Routing;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Exception\CustomerNotLoggedInException;
use Shopware\Core\Checkout\Customer\Event\CustomerLoginEvent;
use Shopware\Core\Checkout\Customer\Event\CustomerLogoutEvent;
use Shopware\Core\Framework\Routing\Event\SalesChannelContextResolvedEvent;
use Shopware\Core\Framework\Routing\Exception\CustomerNotLoggedInRoutingException;
use Shopware\Core\Framework\Routing\KernelListenerPriorities;
use Shopware\Core\Framework\Routing\RoutingException;
use Shopware\Core\PlatformRequest;
use Shopware\Core\SalesChannelRequest;
use Shopware\Core\Test\Generator;
use Shopware\Core\Test\Stub\SystemConfigService\StaticSystemConfigService;
use Shopware\Storefront\Framework\Routing\MaintenanceModeResolver;
use Shopware\Storefront\Framework\Routing\StorefrontRouteScope;
use Shopware\Storefront\Framework\Routing\StorefrontSubscriber;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\RouterInterface;

/**
 * @internal
 */
#[CoversClass(StorefrontSubscriber::class)]
class StorefrontSubscriberTest extends TestCase
{
    public function testHasEvents(): void
    {
        $expected = [
            KernelEvents::REQUEST => [
                ['startSession', 40],
                ['maintenanceResolver'],
            ],
            KernelEvents::EXCEPTION => [
                ['customerNotLoggedInHandler'],
                ['maintenanceResolver'],
            ],
            KernelEvents::CONTROLLER => [
                ['preventPageLoadingFromXmlHttpRequest', KernelListenerPriorities::KERNEL_CONTROLLER_EVENT_SCOPE_VALIDATE],
            ],
            CustomerLoginEvent::class => [
                'updateSessionAfterLogin',
            ],
            CustomerLogoutEvent::class => [
                'updateSessionAfterLogout',
            ],
            SalesChannelContextResolvedEvent::class => [
                ['replaceContextToken'],
            ],
        ];

        static::assertSame($expected, StorefrontSubscriber::getSubscribedEvents());
    }

    public function testMaintenanceRedirect(): void
    {
        $maintenanceModeResolver = $this->createMock(MaintenanceModeResolver::class);
        $maintenanceModeResolver
            ->method('shouldRedirect')
            ->willReturn(true);

        $router = $this->createMock(RouterInterface::class);
        $router->method('generate')->willReturn('/maintenance');

        $storefrontSubscriber = new StorefrontSubscriber(
            new RequestStack(),
            $router,
            $maintenanceModeResolver,
            new StaticSystemConfigService(),
        );

        $event = new RequestEvent(
            $this->createMock(HttpKernelInterface::class),
            new Request(),
            HttpKernelInterface::MAIN_REQUEST
        );

        $storefrontSubscriber->maintenanceResolver($event);

        static::assertTrue($event->hasResponse());
        static::assertInstanceOf(RedirectResponse::class, $event->getResponse());
        static::assertSame('/maintenance', $event->getResponse()->getTargetUrl());
    }

    public function testRedirectLoginPageWhenCustomerNotLoggedInWithRoutingException(): void
    {
        $router = $this->createMock(RouterInterface::class);
        $router->expects(static::once())
            ->method('generate')
            ->with('frontend.account.login.page')
            ->willReturn('/login');

        $subscriber = new StorefrontSubscriber(
            $this->createMock(RequestStack::class),
            $router,
            $this->createMock(MaintenanceModeResolver::class),
            new StaticSystemConfigService(),
        );

        $exception = new CustomerNotLoggedInRoutingException(Response::HTTP_FORBIDDEN, RoutingException::CUSTOMER_NOT_LOGGED_IN_CODE, 'Customer is not logged in.');
        $request = new Request();
        $request->attributes->set(SalesChannelRequest::ATTRIBUTE_IS_SALES_CHANNEL_REQUEST, true);

        $event = new ExceptionEvent(
            $this->createMock(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
            $exception
        );

        $subscriber->customerNotLoggedInHandler($event);

        static::assertInstanceOf(RedirectResponse::class, $event->getResponse());
    }

    public function testRedirectCustomerNonStorefrontRequest(): void
    {
        $subscriber = new StorefrontSubscriber(
            $this->createMock(RequestStack::class),
            $this->createMock(RouterInterface::class),
            $this->createMock(MaintenanceModeResolver::class),
            new StaticSystemConfigService(),
        );

        $event = new ExceptionEvent(
            $this->createMock(HttpKernelInterface::class),
            new Request(),
            HttpKernelInterface::MAIN_REQUEST,
            new \RuntimeException('test')
        );

        $subscriber->customerNotLoggedInHandler($event);

        static::assertFalse($event->hasResponse());
    }

    public function testRedirectLoginPageWhenCustomerNotLoggedInWithCustomerNotLoggedInException(): void
    {
        $router = $this->createMock(RouterInterface::class);
        $router->expects(static::once())
            ->method('generate')
            ->with('frontend.account.login.page')
            ->willReturn('/login');

        $subscriber = new StorefrontSubscriber(
            $this->createMock(RequestStack::class),
            $router,
            $this->createMock(MaintenanceModeResolver::class),
            new StaticSystemConfigService(),
        );

        $exception = new CustomerNotLoggedInException(Response::HTTP_FORBIDDEN, RoutingException::CUSTOMER_NOT_LOGGED_IN_CODE, 'Foo test');
        $request = new Request();
        $request->attributes->set(SalesChannelRequest::ATTRIBUTE_IS_SALES_CHANNEL_REQUEST, true);

        $event = new ExceptionEvent(
            $this->createMock(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
            $exception
        );

        $subscriber->customerNotLoggedInHandler($event);

        static::assertInstanceOf(RedirectResponse::class, $event->getResponse());
    }

    public function testCustomerNotLoggedInHandlerWithoutRedirect(): void
    {
        $router = $this->createMock(RouterInterface::class);
        $router->expects(static::never())
            ->method('generate')
            ->with('frontend.account.login.page')
            ->willReturn('/login');

        $subscriber = new StorefrontSubscriber(
            $this->createMock(RequestStack::class),
            $router,
            $this->createMock(MaintenanceModeResolver::class),
            new StaticSystemConfigService(),
        );

        $exception = new RoutingException(Response::HTTP_FORBIDDEN, 'foo', 'You have to be logged in to access this page');
        $request = new Request();
        $request->attributes->set(SalesChannelRequest::ATTRIBUTE_IS_SALES_CHANNEL_REQUEST, true);

        $event = new ExceptionEvent(
            $this->createMock(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
            $exception
        );

        $subscriber->customerNotLoggedInHandler($event);
    }

    #[DataProvider('dataProviderXMLHttpRequest')]
    public function testNonXmlHttpRequestPassesThrough(Request $request, bool $expected): void
    {
        $storefrontSubscriber = new StorefrontSubscriber(
            new RequestStack(),
            $this->createMock(RouterInterface::class),
            $this->createMock(MaintenanceModeResolver::class),
            new StaticSystemConfigService(),
        );

        $event = new ControllerEvent(
            $this->createMock(HttpKernelInterface::class),
            function (): void {},
            $request,
            HttpKernelInterface::MAIN_REQUEST
        );

        if ($expected) {
            $this->expectExceptionObject(RoutingException::accessDeniedForXmlHttpRequest());
        } else {
            static::assertTrue($event->isMainRequest());
        }

        $storefrontSubscriber->preventPageLoadingFromXmlHttpRequest($event);
    }

    public static function dataProviderXMLHttpRequest(): \Generator
    {
        yield 'not an XMLHttpRequest' => [
            'request' => new Request(),
            'expected' => false,
        ];

        yield 'XMLHttpRequest, but not a storefront request' => [
            'request' => new Request([], [], [], [], [], ['HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest']),
            'expected' => false,
        ];

        yield 'XMLHttpRequest, but a storefront request and not allowed' => [
            'request' => new Request([], [], [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StorefrontRouteScope::ID]], [], [], ['HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest']),
            'expected' => true,
        ];

        yield 'XMLHttpRequest, but a storefront request and allowed' => [
            'request' => new Request([], [], [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StorefrontRouteScope::ID], 'XmlHttpRequest' => true], [], [], ['HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest']),
            'expected' => false,
        ];
    }

    public function testStartSession(): void
    {
        $request = new Request([], [], [SalesChannelRequest::ATTRIBUTE_IS_SALES_CHANNEL_REQUEST => true, PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT => Generator::createSalesChannelContext()], [], [], ['HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest']);
        $request->setSession(new Session(new MockArraySessionStorage()));
        $requestStack = new RequestStack();

        $requestStack->push($request);

        $subscriber = new StorefrontSubscriber(
            $requestStack,
            $this->createMock(RouterInterface::class),
            $this->createMock(MaintenanceModeResolver::class),
            new StaticSystemConfigService(),
        );

        $subscriber->startSession();

        static::assertTrue($request->getSession()->has('sessionId'));
    }
}
