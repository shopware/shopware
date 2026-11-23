<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DependencyInjection\CompilerPass;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DependencyInjection\CompilerPass\RouteScopeCompilerPass;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\ApiRouteScope;
use Shopware\Core\Framework\Routing\RouteScope;
use Shopware\Core\Framework\Routing\StoreApiRouteScope;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(RouteScopeCompilerPass::class)]
class RouteScopeCompilerPassTest extends TestCase
{
    public function testCollectsPrefixesOfAllRouteScopesAndOfApiContextRouteScopes(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition(ApiRouteScope::class, (new Definition(ApiRouteScope::class))->addTag('shopware.route_scope'));
        $container->setDefinition(StoreApiRouteScope::class, (new Definition(StoreApiRouteScope::class))->addTag('shopware.route_scope'));
        $container->setDefinition(RouteScope::class, (new Definition(RouteScope::class))->addTag('shopware.route_scope'));

        (new RouteScopeCompilerPass())->process($container);

        static::assertSame(
            ['api', 'sw-domain-hash.html', 'store-api', '_wdt', '_profiler', '_error'],
            $container->getParameter('shopware.routing.registered_api_prefixes')
        );
        static::assertSame(
            ['api', 'sw-domain-hash.html'],
            $container->getParameter('shopware.routing.api_context_route_prefixes')
        );
    }
}
