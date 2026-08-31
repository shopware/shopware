<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Controller;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\Test\Generator;
use Shopware\Storefront\Controller\MaintenanceController;
use Shopware\Storefront\Framework\Routing\MaintenanceModeResolver;
use Shopware\Storefront\Page\Maintenance\MaintenancePageLoader;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(MaintenanceController::class)]
class MaintenanceControllerTest extends TestCase
{
    public function testMaintenanceRedirectToShopWithRedirectTo(): void
    {
        $maintenanceModeResolver = static::createStub(MaintenanceModeResolver::class);
        $maintenanceModeResolver->method('shouldRedirectToShop')
            ->willReturn(true);

        $controller = new MaintenanceController(
            static::createStub(SystemConfigService::class),
            static::createStub(MaintenancePageLoader::class),
            $maintenanceModeResolver
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
}
