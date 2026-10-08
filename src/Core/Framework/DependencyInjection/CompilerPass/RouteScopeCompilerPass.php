<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DependencyInjection\CompilerPass;

use Shopware\Core\Framework\Deprecation\BCChange\BecomesInternal;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\AbstractRouteScope;
use Shopware\Core\Framework\Routing\ApiContextRouteScopeDependant;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

#[Package('framework')]
#[BecomesInternal(version: 'v6.8.0')]
class RouteScopeCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $routeScopeDefinitions = $container->findTaggedServiceIds('shopware.route_scope');

        $apiPrefixes = [];
        $apiContextPrefixes = [];
        foreach (array_keys($routeScopeDefinitions) as $definition) {
            $routeScope = $container->get($definition);

            if (!$routeScope instanceof AbstractRouteScope) {
                continue;
            }

            $apiPrefixes = array_merge($apiPrefixes, $routeScope->getRoutePrefixes());

            if ($routeScope instanceof ApiContextRouteScopeDependant) {
                $apiContextPrefixes = array_merge($apiContextPrefixes, $routeScope->getRoutePrefixes());
            }
        }

        $container->setParameter('shopware.routing.registered_api_prefixes', $apiPrefixes);
        $container->setParameter('shopware.routing.api_context_route_prefixes', array_values(array_unique($apiContextPrefixes)));
    }
}
