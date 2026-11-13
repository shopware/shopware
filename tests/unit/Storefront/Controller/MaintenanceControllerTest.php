<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Controller;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\RateLimiter\RateLimiter;
use Shopware\Core\PlatformRequest;
use Shopware\Core\SalesChannelRequest;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\Test\Generator;
use Shopware\Storefront\Controller\MaintenanceController;
use Shopware\Storefront\Framework\Routing\MaintenanceModeResolver;
use Shopware\Storefront\Page\Maintenance\MaintenancePageLoader;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(MaintenanceController::class)]
class MaintenanceControllerTest extends TestCase
{
    private const SALES_CHANNEL_ID = 'sales-channel-id';

    public function testMaintenanceRedirectToShopWithRedirectTo(): void
    {
        $maintenanceModeResolver = static::createStub(MaintenanceModeResolver::class);
        $maintenanceModeResolver->method('shouldRedirectToShop')
            ->willReturn(true);

        $controller = new MaintenanceController(
            static::createStub(SystemConfigService::class),
            static::createStub(MaintenancePageLoader::class),
            $maintenanceModeResolver,
            static::createStub(RateLimiter::class)
        );

        $router = $this->createMock(RouterInterface::class);
        $router
            ->expects($this->once())
            ->method('generate')
            ->with('foo', ['foo' => 'bar'], UrlGeneratorInterface::ABSOLUTE_PATH)
            ->willReturn('/foo/generated');

        $request = new Request(
            [
                'redirectTo' => 'foo',
                'redirectParameters' => ['foo' => 'bar'],
            ]
        );

        $container = new ContainerBuilder();
        $container->set('router', $router);
        $container->set('event_dispatcher', static::createStub(EventDispatcherInterface::class));

        $controller->setContainer($container);

        $context = Generator::generateSalesChannelContext();

        $response = $controller->renderMaintenancePage($request, $context);

        static::assertInstanceOf(RedirectResponse::class, $response);
        static::assertSame('/foo/generated', $response->getTargetUrl());
    }

    public function testRedirectToShopIsExcludedFromTheHttpCache(): void
    {
        $maintenanceModeResolver = static::createStub(MaintenanceModeResolver::class);
        $maintenanceModeResolver->method('shouldRedirectToShop')
            ->willReturn(true);

        $controller = new MaintenanceController(
            static::createStub(SystemConfigService::class),
            static::createStub(MaintenancePageLoader::class),
            $maintenanceModeResolver,
            static::createStub(RateLimiter::class)
        );

        $router = static::createStub(RouterInterface::class);
        $router->method('generate')->willReturn('/');

        $container = new ContainerBuilder();
        $container->set('router', $router);
        $container->set('event_dispatcher', static::createStub(EventDispatcherInterface::class));

        $controller->setContainer($container);

        $request = new Request(attributes: [PlatformRequest::ATTRIBUTE_HTTP_CACHE => true]);

        $response = $controller->renderMaintenancePage($request, Generator::generateSalesChannelContext());

        static::assertInstanceOf(RedirectResponse::class, $response);
        static::assertFalse($request->attributes->get(PlatformRequest::ATTRIBUTE_HTTP_CACHE));
    }

    public function testValidMaintenancePasswordCreatesSessionGrantAndRegeneratesSessionId(): void
    {
        $rateLimiter = $this->createMock(RateLimiter::class);
        $rateLimiter->expects($this->once())->method('ensureAcceptedIfConfigured');
        $rateLimiter->expects($this->once())->method('resetIfConfigured');
        $controller = $this->createMaintenanceController($rateLimiter);
        $request = $this->createPasswordRequest('correct-password');
        $session = $request->getSession();
        $hash = password_hash('correct-password', \PASSWORD_DEFAULT);
        $generation = 'generation-id';
        $request->attributes->set(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_ID, self::SALES_CHANNEL_ID);
        $request->attributes->set(SalesChannelRequest::ATTRIBUTE_SALES_CHANNEL_MAINTENANCE_PASSWORD, $hash);
        $request->attributes->set(SalesChannelRequest::ATTRIBUTE_SALES_CHANNEL_MAINTENANCE_PASSWORD_GENERATION, $generation);

        $response = $controller->authenticateMaintenancePassword($request);

        static::assertInstanceOf(RedirectResponse::class, $response);
        static::assertSame('/frontend.maintenance.page', $response->getTargetUrl());
        static::assertSame($hash . ':' . $generation, $session->get('maintenance_access_' . self::SALES_CHANNEL_ID));
    }

    public function testIncorrectMaintenancePasswordDoesNotCreateSessionGrant(): void
    {
        $rateLimiter = $this->createMock(RateLimiter::class);
        $rateLimiter->expects($this->once())->method('ensureAcceptedIfConfigured');
        $rateLimiter->expects($this->never())->method('resetIfConfigured');
        $controller = $this->createMaintenanceController($rateLimiter);
        $request = $this->createPasswordRequest('incorrect-password');
        $session = $request->getSession();
        $request->attributes->set(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_ID, self::SALES_CHANNEL_ID);
        $request->attributes->set(SalesChannelRequest::ATTRIBUTE_SALES_CHANNEL_MAINTENANCE_PASSWORD, password_hash('correct-password', \PASSWORD_DEFAULT));

        $response = $controller->authenticateMaintenancePassword($request);

        static::assertInstanceOf(RedirectResponse::class, $response);
        static::assertSame('/frontend.maintenance.password.page', $response->getTargetUrl());
        static::assertFalse($session->has('maintenance_access_' . self::SALES_CHANNEL_ID));
    }

    public function testMaintenancePasswordLongerThanShopwareMaximumDoesNotCreateSessionGrant(): void
    {
        $controller = $this->createMaintenanceController();
        $request = $this->createPasswordRequest(str_repeat('a', 4097));
        $session = $request->getSession();
        $request->attributes->set(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_ID, self::SALES_CHANNEL_ID);
        $request->attributes->set(SalesChannelRequest::ATTRIBUTE_SALES_CHANNEL_MAINTENANCE_PASSWORD, password_hash(str_repeat('a', 4097), \PASSWORD_DEFAULT));

        $controller->authenticateMaintenancePassword($request);

        static::assertFalse($session->has('maintenance_access_' . self::SALES_CHANNEL_ID));
    }

    public function testMaintenancePasswordPageRedirectsToMaintenancePageWithoutPassword(): void
    {
        $maintenanceModeResolver = static::createStub(MaintenanceModeResolver::class);
        $maintenanceModeResolver->method('isMaintenanceRequest')->willReturn(true);
        $controller = $this->createMaintenanceController(maintenanceModeResolver: $maintenanceModeResolver);

        $response = $controller->renderMaintenancePasswordPage(new Request());

        static::assertInstanceOf(RedirectResponse::class, $response);
        static::assertSame('/frontend.maintenance.page', $response->getTargetUrl());
    }

    public function testMaintenancePasswordPageRedirectsToMaintenancePageForAllowedClients(): void
    {
        $maintenanceModeResolver = static::createStub(MaintenanceModeResolver::class);
        $maintenanceModeResolver->method('isMaintenanceRequest')->willReturn(false);
        $controller = $this->createMaintenanceController(maintenanceModeResolver: $maintenanceModeResolver);
        $request = new Request();
        $request->attributes->set(SalesChannelRequest::ATTRIBUTE_SALES_CHANNEL_MAINTENANCE_PASSWORD, password_hash('correct-password', \PASSWORD_DEFAULT));

        $response = $controller->renderMaintenancePasswordPage($request);

        static::assertInstanceOf(RedirectResponse::class, $response);
        static::assertSame('/frontend.maintenance.page', $response->getTargetUrl());
    }

    private function createMaintenanceController(
        ?RateLimiter $rateLimiter = null,
        ?MaintenanceModeResolver $maintenanceModeResolver = null,
    ): MaintenanceController {
        $controller = new MaintenanceController(
            static::createStub(SystemConfigService::class),
            static::createStub(MaintenancePageLoader::class),
            $maintenanceModeResolver ?? static::createStub(MaintenanceModeResolver::class),
            $rateLimiter ?? static::createStub(RateLimiter::class)
        );

        $router = static::createStub(RouterInterface::class);
        $router->method('generate')->willReturnCallback(static fn (string $name): string => '/' . $name);
        $container = new ContainerBuilder();
        $container->set('router', $router);
        $container->set('event_dispatcher', static::createStub(EventDispatcherInterface::class));
        $controller->setContainer($container);

        return $controller;
    }

    private function createPasswordRequest(string $password): Request
    {
        $request = Request::create('/maintenance/password', Request::METHOD_POST, [
            'password' => $password,
        ]);
        $session = new Session(new MockArraySessionStorage());
        $request->setSession($session);

        return $request;
    }
}
