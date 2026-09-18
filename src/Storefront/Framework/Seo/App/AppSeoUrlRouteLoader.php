<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Seo\App;

use Shopware\Core\Content\Seo\ConfiguredEntitySeoUrlRoute;
use Shopware\Core\Content\Seo\SeoUrlRoute\SeoUrlRouteLoaderInterface;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('inventory')]
class AppSeoUrlRouteLoader implements SeoUrlRouteLoaderInterface
{
    public function __construct(
        private readonly AppSeoUrlRouteProvider $routes,
        private readonly DefinitionInstanceRegistry $definitionRegistry,
    ) {
    }

    public function load(): iterable
    {
        foreach ($this->routes->getEntityRoutes() as $route) {
            if ($route->entityName === null || $route->defaultTemplate === null || !$this->definitionRegistry->has($route->entityName)) {
                continue;
            }

            yield new ConfiguredEntitySeoUrlRoute(new AppSeoUrlRoute(
                $this->definitionRegistry->getByEntityName($route->entityName),
                $route->routeName,
                $route->hook,
                $route->defaultTemplate,
            ));
        }
    }
}
