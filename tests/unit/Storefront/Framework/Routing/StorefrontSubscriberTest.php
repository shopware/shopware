<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Framework\Routing;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\CartException;
use Shopware\Core\Checkout\Customer\Event\CustomerLoginEvent;
use Shopware\Core\Checkout\Customer\Event\CustomerLogoutEvent;
use Shopware\Core\Framework\Routing\Event\SalesChannelContextResolvedEvent;
use Shopware\Core\Framework\Routing\KernelListenerPriorities;
use Shopware\Core\Framework\Routing\RoutingException;
use Shopware\Core\PlatformRequest;
use Shopware\Core\SalesChannelRequest;
use Shopware\Core\Test\Generator;
use Shopware\Core\Test\Stub\SystemConfigService\StaticSystemConfigService;
use Shopware\Storefront\Event\MaintenanceRedirectEvent;
use Shopware\Storefront\Framework\Routing\MaintenanceModeResolver;
use Shopware\Storefront\Framework\Routing\StorefrontRouteScope;
use Shopware\Storefront\Framework\Routing\StorefrontSubscriber;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
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

        $eventDispatcher = new EventDispatcher();
        $eventIsThrown = false;
        $eventDispatcher->addListener(
            MaintenanceRedirectEvent::class,
            static function () use (&$eventIsThrown): void {
                $eventIsThrown = true;
            }
        );

        (new StorefrontSubscriber(
            new RequestStack(),
            $router,
            $maintenanceModeResolver,
            new StaticSystemConfigService(),
            $eventDispatcher,
        ))->maintenanceResolver($event);

        $response = $event->getResponse();
        static::assertInstanceOf(RedirectResponse::class, $response);
        static::assertSame('/maintenance', $response->getTargetUrl());
        static::assertTrue($eventIsThrown);
    }

    public function testMaintenanceParametersRedirect(): void
    {
        $maintenanceModeResolver = $this->createMock(MaintenanceModeResolver::class);
        $maintenanceModeResolver
            ->method('shouldRedirect')
            ->willReturn(true);

        $router = $this->createMock(RouterInterface::class);
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
            $this->createMock(HttpKernelInterface::class),
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

        (new StorefrontSubscriber(
            new RequestStack(),
            $router,
            $maintenanceModeResolver,
            new StaticSystemConfigService(),
            $eventDispatcher,
        ))->maintenanceResolver($event);

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
            $this->createMock(HttpKernelInterface::class),
            new Request(
                attributes: [SalesChannelRequest::ATTRIBUTE_IS_SALES_CHANNEL_REQUEST => true],
                server: $server,
            ),
            HttpKernelInterface::MAIN_REQUEST,
            $exception
        );

        (new StorefrontSubscriber(
            $this->createMock(RequestStack::class),
            $router,
            $this->createMock(MaintenanceModeResolver::class),
            new StaticSystemConfigService(),
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
            $this->createMock(HttpKernelInterface::class),
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
            new RequestStack(),
            $this->createMock(RouterInterface::class),
            $this->createMock(MaintenanceModeResolver::class),
            new StaticSystemConfigService(),
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
            new EventDispatcher(),
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
            new EventDispatcher(),
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
            new StaticSystemConfigService(),
            new EventDispatcher(),
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
            new StaticSystemConfigService(),
            new EventDispatcher(),
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
            new StaticSystemConfigService(),
            new EventDispatcher(),
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
            new StaticSystemConfigService(),
            new EventDispatcher(),
        ))->updateSession(self::TEST_CONTEXT_TOKEN);

        static::assertSame(self::TEST_CONTEXT_TOKEN, $request->getSession()->get(PlatformRequest::HEADER_CONTEXT_TOKEN));
        static::assertSame(self::TEST_CONTEXT_TOKEN, $request->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN));
    }

    public function testStartSessionWithBindingDisabledUsesDefaultTokenKey(): void
    {
        $salesChannelId = 'test-sales-channel-id';
        $request = new Request(
            attributes: [
                SalesChannelRequest::ATTRIBUTE_IS_SALES_CHANNEL_REQUEST => true,
                PlatformRequest::ATTRIBUTE_SALES_CHANNEL_ID => $salesChannelId,
            ]
        );
        $request->setSession(new Session(new MockArraySessionStorage()));
        $requestStack = new RequestStack([$request]);

        $configService = new StaticSystemConfigService([
            'core.systemWideLoginRegistration.isCustomerBoundToSalesChannel' => false,
        ]);

        (new StorefrontSubscriber(
            $requestStack,
            $this->createMock(RouterInterface::class),
            $this->createMock(MaintenanceModeResolver::class),
            $configService,
            new EventDispatcher(),
        ))->startSession();

        // Should use default token key
        static::assertTrue($request->getSession()->has(PlatformRequest::HEADER_CONTEXT_TOKEN));
        // Should NOT use channel-specific key
        static::assertFalse($request->getSession()->has(PlatformRequest::HEADER_CONTEXT_TOKEN . '-' . $salesChannelId));
    }

    public function testStartSessionWithBindingEnabledUsesChannelSpecificTokenKey(): void
    {
        $salesChannelId = 'test-sales-channel-id';
        $request = new Request(
            attributes: [
                SalesChannelRequest::ATTRIBUTE_IS_SALES_CHANNEL_REQUEST => true,
                PlatformRequest::ATTRIBUTE_SALES_CHANNEL_ID => $salesChannelId,
            ]
        );
        $request->setSession(new Session(new MockArraySessionStorage()));
        $requestStack = new RequestStack([$request]);

        $configService = new StaticSystemConfigService([
            'core.systemWideLoginRegistration.isCustomerBoundToSalesChannel' => true,
        ]);

        (new StorefrontSubscriber(
            $requestStack,
            $this->createMock(RouterInterface::class),
            $this->createMock(MaintenanceModeResolver::class),
            $configService,
            new EventDispatcher(),
        ))->startSession();

        // Should use channel-specific token key
        $channelTokenKey = PlatformRequest::HEADER_CONTEXT_TOKEN . '-' . $salesChannelId;
        static::assertTrue($request->getSession()->has($channelTokenKey));

        // Token should be set in request header
        static::assertNotNull($request->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN));
        static::assertSame(
            $request->getSession()->get($channelTokenKey),
            $request->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN)
        );
    }

    public function testStartSessionWithBindingEnabledPreservesTokensAcrossChannels(): void
    {
        $salesChannelIdA = 'sales-channel-a';
        $salesChannelIdB = 'sales-channel-b';

        $session = new Session(new MockArraySessionStorage());

        $configService = new StaticSystemConfigService([
            'core.systemWideLoginRegistration.isCustomerBoundToSalesChannel' => true,
        ]);

        // Visit Channel A
        $requestA = new Request(
            attributes: [
                SalesChannelRequest::ATTRIBUTE_IS_SALES_CHANNEL_REQUEST => true,
                PlatformRequest::ATTRIBUTE_SALES_CHANNEL_ID => $salesChannelIdA,
            ]
        );
        $requestA->setSession($session);
        $requestStackA = new RequestStack([$requestA]);

        (new StorefrontSubscriber(
            $requestStackA,
            $this->createMock(RouterInterface::class),
            $this->createMock(MaintenanceModeResolver::class),
            $configService,
            new EventDispatcher(),
        ))->startSession();

        $tokenA = $session->get(PlatformRequest::HEADER_CONTEXT_TOKEN . '-' . $salesChannelIdA);
        static::assertNotNull($tokenA);

        // Visit Channel B
        $requestB = new Request(
            attributes: [
                SalesChannelRequest::ATTRIBUTE_IS_SALES_CHANNEL_REQUEST => true,
                PlatformRequest::ATTRIBUTE_SALES_CHANNEL_ID => $salesChannelIdB,
            ]
        );
        $requestB->setSession($session);
        $requestStackB = new RequestStack([$requestB]);

        (new StorefrontSubscriber(
            $requestStackB,
            $this->createMock(RouterInterface::class),
            $this->createMock(MaintenanceModeResolver::class),
            $configService,
            new EventDispatcher(),
        ))->startSession();

        $tokenB = $session->get(PlatformRequest::HEADER_CONTEXT_TOKEN . '-' . $salesChannelIdB);
        static::assertNotNull($tokenB);
        static::assertNotSame($tokenA, $tokenB);

        // Return to Channel A - token should be preserved
        $requestA2 = new Request(
            attributes: [
                SalesChannelRequest::ATTRIBUTE_IS_SALES_CHANNEL_REQUEST => true,
                PlatformRequest::ATTRIBUTE_SALES_CHANNEL_ID => $salesChannelIdA,
            ]
        );
        $requestA2->setSession($session);
        $requestStackA2 = new RequestStack([$requestA2]);

        (new StorefrontSubscriber(
            $requestStackA2,
            $this->createMock(RouterInterface::class),
            $this->createMock(MaintenanceModeResolver::class),
            $configService,
            new EventDispatcher(),
        ))->startSession();

        $tokenA2 = $session->get(PlatformRequest::HEADER_CONTEXT_TOKEN . '-' . $salesChannelIdA);
        static::assertSame($tokenA, $tokenA2, 'Token for Channel A should be preserved');

        // Both tokens should still exist
        static::assertTrue($session->has(PlatformRequest::HEADER_CONTEXT_TOKEN . '-' . $salesChannelIdA));
        static::assertTrue($session->has(PlatformRequest::HEADER_CONTEXT_TOKEN . '-' . $salesChannelIdB));
    }

    public function testUpdateSessionWithBindingEnabledStoresTokenInChannelKey(): void
    {
        $salesChannelId = 'test-sales-channel-id';
        $newToken = 'new-context-token';

        $request = new Request(
            attributes: [
                SalesChannelRequest::ATTRIBUTE_IS_SALES_CHANNEL_REQUEST => true,
                PlatformRequest::ATTRIBUTE_SALES_CHANNEL_ID => $salesChannelId,
            ]
        );
        $request->setSession(new Session(new MockArraySessionStorage()));
        $requestStack = new RequestStack([$request]);

        $configService = new StaticSystemConfigService([
            'core.systemWideLoginRegistration.isCustomerBoundToSalesChannel' => true,
        ]);

        (new StorefrontSubscriber(
            $requestStack,
            $this->createMock(RouterInterface::class),
            $this->createMock(MaintenanceModeResolver::class),
            $configService,
            new EventDispatcher(),
        ))->updateSession($newToken);

        // Should store in both channel-specific and default keys
        $channelTokenKey = PlatformRequest::HEADER_CONTEXT_TOKEN . '-' . $salesChannelId;
        static::assertSame($newToken, $request->getSession()->get($channelTokenKey));
        static::assertSame($newToken, $request->getSession()->get(PlatformRequest::HEADER_CONTEXT_TOKEN));
        static::assertSame($newToken, $request->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN));
    }

    public function testUpdateSessionWithBindingDisabledStoresTokenInDefaultKeyOnly(): void
    {
        $salesChannelId = 'test-sales-channel-id';
        $newToken = 'new-context-token';

        $request = new Request(
            attributes: [
                SalesChannelRequest::ATTRIBUTE_IS_SALES_CHANNEL_REQUEST => true,
                PlatformRequest::ATTRIBUTE_SALES_CHANNEL_ID => $salesChannelId,
            ]
        );
        $request->setSession(new Session(new MockArraySessionStorage()));
        $requestStack = new RequestStack([$request]);

        $configService = new StaticSystemConfigService([
            'core.systemWideLoginRegistration.isCustomerBoundToSalesChannel' => false,
        ]);

        (new StorefrontSubscriber(
            $requestStack,
            $this->createMock(RouterInterface::class),
            $this->createMock(MaintenanceModeResolver::class),
            $configService,
            new EventDispatcher(),
        ))->updateSession($newToken);

        // Should only store in default key
        static::assertSame($newToken, $request->getSession()->get(PlatformRequest::HEADER_CONTEXT_TOKEN));
        // Should NOT store in channel-specific key
        $channelTokenKey = PlatformRequest::HEADER_CONTEXT_TOKEN . '-' . $salesChannelId;
        static::assertFalse($request->getSession()->has($channelTokenKey));
    }
}
