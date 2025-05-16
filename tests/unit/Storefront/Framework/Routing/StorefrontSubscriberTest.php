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
    private const TEST_CONTEXT_TOKEN = 'test-context-token';

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

        $event = new RequestEvent(
            $this->createMock(HttpKernelInterface::class),
            new Request(),
            HttpKernelInterface::MAIN_REQUEST
        );

        (new StorefrontSubscriber(
            new RequestStack(),
            $router,
            $maintenanceModeResolver,
            new StaticSystemConfigService(),
        ))->maintenanceResolver($event);

        $response = $event->getResponse();
        static::assertInstanceOf(RedirectResponse::class, $response);
        static::assertSame('/maintenance', $response->getTargetUrl());
    }

    public function testRedirectLoginPageWhenCustomerNotLoggedInWithRoutingException(): void
    {
        $router = $this->createMock(RouterInterface::class);
        $router->expects($this->once())
            ->method('generate')
            ->with('frontend.account.login.page')
            ->willReturn('/login');

        $event = new ExceptionEvent(
            $this->createMock(HttpKernelInterface::class),
            new Request(attributes: [SalesChannelRequest::ATTRIBUTE_IS_SALES_CHANNEL_REQUEST => true]),
            HttpKernelInterface::MAIN_REQUEST,
            new CustomerNotLoggedInRoutingException(
                Response::HTTP_FORBIDDEN,
                RoutingException::CUSTOMER_NOT_LOGGED_IN_CODE,
                'Customer is not logged in.'
            )
        );

        (new StorefrontSubscriber(
            $this->createMock(RequestStack::class),
            $router,
            $this->createMock(MaintenanceModeResolver::class),
            new StaticSystemConfigService(),
        ))->customerNotLoggedInHandler($event);

        static::assertInstanceOf(RedirectResponse::class, $event->getResponse());
    }

    public function testRedirectCustomerNonStorefrontRequest(): void
    {
        $event = new ExceptionEvent(
            $this->createMock(HttpKernelInterface::class),
            new Request(),
            HttpKernelInterface::MAIN_REQUEST,
            new \RuntimeException('test')
        );

        (new StorefrontSubscriber(
            $this->createMock(RequestStack::class),
            $this->createMock(RouterInterface::class),
            $this->createMock(MaintenanceModeResolver::class),
            new StaticSystemConfigService(),
        ))->customerNotLoggedInHandler($event);

        static::assertFalse($event->hasResponse());
    }

    public function testRedirectLoginPageWhenCustomerNotLoggedInWithCustomerNotLoggedInException(): void
    {
        $router = $this->createMock(RouterInterface::class);
        $router->expects($this->once())
            ->method('generate')
            ->with('frontend.account.login.page')
            ->willReturn('/login');

        $event = new ExceptionEvent(
            $this->createMock(HttpKernelInterface::class),
            new Request(attributes: [SalesChannelRequest::ATTRIBUTE_IS_SALES_CHANNEL_REQUEST => true]),
            HttpKernelInterface::MAIN_REQUEST,
            new CustomerNotLoggedInException(
                Response::HTTP_FORBIDDEN,
                RoutingException::CUSTOMER_NOT_LOGGED_IN_CODE,
                'Foo test'
            )
        );

        (new StorefrontSubscriber(
            $this->createMock(RequestStack::class),
            $router,
            $this->createMock(MaintenanceModeResolver::class),
            new StaticSystemConfigService(),
        ))->customerNotLoggedInHandler($event);

        static::assertInstanceOf(RedirectResponse::class, $event->getResponse());
    }

    public function testCustomerNotLoggedInHandlerWithoutRedirect(): void
    {
        $router = $this->createMock(RouterInterface::class);
        $router->expects($this->never())
            ->method('generate')
            ->with('frontend.account.login.page')
            ->willReturn('/login');

        $event = new ExceptionEvent(
            $this->createMock(HttpKernelInterface::class),
            new Request(attributes: [SalesChannelRequest::ATTRIBUTE_IS_SALES_CHANNEL_REQUEST => true]),
            HttpKernelInterface::MAIN_REQUEST,
            new RoutingException(Response::HTTP_FORBIDDEN, 'foo', 'You have to be logged in to access this page')
        );

        (new StorefrontSubscriber(
            $this->createMock(RequestStack::class),
            $router,
            $this->createMock(MaintenanceModeResolver::class),
            new StaticSystemConfigService(),
        ))->customerNotLoggedInHandler($event);
    }

    #[DataProvider('dataProviderXMLHttpRequest')]
    public function testNonXmlHttpRequestPassesThrough(Request $request, bool $expected): void
    {
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

        (new StorefrontSubscriber(
            new RequestStack(),
            $this->createMock(RouterInterface::class),
            $this->createMock(MaintenanceModeResolver::class),
            new StaticSystemConfigService(),
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

    public function testStartSession(): void
    {
        $request = new Request(
            attributes: [
                SalesChannelRequest::ATTRIBUTE_IS_SALES_CHANNEL_REQUEST => true,
                PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT => Generator::generateSalesChannelContext(),
            ],
            server: ['HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest']
        );
        $request->setSession(new Session(new MockArraySessionStorage()));
        $requestStack = new RequestStack();

        $requestStack->push($request);

        (new StorefrontSubscriber(
            $requestStack,
            $this->createMock(RouterInterface::class),
            $this->createMock(MaintenanceModeResolver::class),
            new StaticSystemConfigService(),
        ))->startSession();

        static::assertTrue($request->getSession()->has('sessionId'));
    }

    public function testSubRequestShouldGetSameContextTokenAsMainRequest(): void
    {
        $mainRequest = new Request(
            attributes: [
                SalesChannelRequest::ATTRIBUTE_IS_SALES_CHANNEL_REQUEST => true,
                PlatformRequest::ATTRIBUTE_SALES_CHANNEL_ID => 'test-sales-channel-id',
            ]
        );

        $session = new Session(new MockArraySessionStorage());
        $session->set(PlatformRequest::HEADER_CONTEXT_TOKEN, self::TEST_CONTEXT_TOKEN);
        $mainRequest->setSession($session);

        $subRequest = new Request();
        $requestStack = new RequestStack([$mainRequest, $subRequest]);

        (new StorefrontSubscriber(
            $requestStack,
            $this->createMock(RouterInterface::class),
            $this->createMock(MaintenanceModeResolver::class),
            new StaticSystemConfigService(),
        ))->startSession();

        $subRequestContextToken = $subRequest->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN);
        static::assertSame(self::TEST_CONTEXT_TOKEN, $subRequestContextToken);
        static::assertSame($mainRequest->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN), $subRequestContextToken);
    }

    public function testUpdateSessionWithoutRequest(): void
    {
        $requestStack = new RequestStack();

        (new StorefrontSubscriber(
            $requestStack,
            $this->createMock(RouterInterface::class),
            $this->createMock(MaintenanceModeResolver::class),
            new StaticSystemConfigService()
        ))->updateSession(self::TEST_CONTEXT_TOKEN);

        static::assertNull($requestStack->getCurrentRequest());
    }

    public function testUpdateSessionIsNoSalesChannelRequest(): void
    {
        $request = new Request();

        (new StorefrontSubscriber(
            new RequestStack([$request]),
            $this->createMock(RouterInterface::class),
            $this->createMock(MaintenanceModeResolver::class),
            new StaticSystemConfigService()
        ))->updateSession(self::TEST_CONTEXT_TOKEN);

        static::assertNull($request->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN));
    }

    public function testUpdateSessionWithoutSession(): void
    {
        $request = new Request(attributes: [SalesChannelRequest::ATTRIBUTE_IS_SALES_CHANNEL_REQUEST => true]);
        $requestStack = new RequestStack([$request]);

        (new StorefrontSubscriber(
            $requestStack,
            $this->createMock(RouterInterface::class),
            $this->createMock(MaintenanceModeResolver::class),
            new StaticSystemConfigService()
        ))->updateSession(self::TEST_CONTEXT_TOKEN);

        static::assertNull($request->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN));
    }

    public function testUpdateSession(): void
    {
        $request = new Request(attributes: [SalesChannelRequest::ATTRIBUTE_IS_SALES_CHANNEL_REQUEST => true]);
        $request->setSession(new Session(new MockArraySessionStorage()));
        $requestStack = new RequestStack([$request]);

        (new StorefrontSubscriber(
            $requestStack,
            $this->createMock(RouterInterface::class),
            $this->createMock(MaintenanceModeResolver::class),
            new StaticSystemConfigService()
        ))->updateSession(self::TEST_CONTEXT_TOKEN);

        static::assertSame(self::TEST_CONTEXT_TOKEN, $request->getSession()->get(PlatformRequest::HEADER_CONTEXT_TOKEN));
        static::assertSame(self::TEST_CONTEXT_TOKEN, $request->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN));
    }
}
