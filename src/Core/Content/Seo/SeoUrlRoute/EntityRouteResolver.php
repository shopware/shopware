<?php declare(strict_types=1);

namespace Shopware\Core\Content\Seo\SeoUrlRoute;

use Shopware\Core\Content\Seo\Exception\SeoUrlRouteConfigException;
use Shopware\Core\Content\Seo\SeoUrlPlaceholderHandlerInterface;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Routing\RouterInterface;

#[Package('inventory')]
class EntityRouteResolver
{
    /**
     * @internal
     *
     * @param iterable<EntitySeoUrlRouteInterface> $storeApiSeoUrlRoutes
     */
    public function __construct(
        private readonly SeoUrlRouteRegistry $registry,
        private readonly SeoUrlPlaceholderHandlerInterface $seoUrlPlaceholderHandler,
        private readonly RouterInterface $router,
        private readonly iterable $storeApiSeoUrlRoutes = [],
    ) {
    }

    public function getRouteNameForEntityName(string $entityName): string
    {
        return $this->getRouteConfig($entityName)->getRouteName();
    }

    /**
     * Generates a SEO URL placeholder for the given entity.
     * Returns store-api route when no route is registered for the entity type (e.g. headless setups).
     *
     * @param list<string> $values
     */
    public function generateSeoUrlPlaceholder(string $entityName, array $values = []): string
    {
        $config = $this->getRouteConfig($entityName);

        return $this->seoUrlPlaceholderHandler->generate($config->getRouteName(), $config->getParameterKeyValuePairs($values));
    }

    /**
     * Generates a concrete URL for the given entity via the Symfony router.
     * Returns store-api route when no route is registered for the entity type (e.g. headless setups).
     *
     * @param list<string> $values
     */
    public function generateUrl(string $entityName, array $values = []): string
    {
        $config = $this->getRouteConfig($entityName);

        return $this->router->generate($config->getRouteName(), $config->getParameterKeyValuePairs($values));
    }

    private function getRouteConfig(string $entityName): SeoUrlRouteConfig
    {
        $routes = $this->registry->findByDefinition($entityName);
        $key = array_key_first($routes);
        $route = $key !== null ? $routes[$key] : null;

        if ($route) {
            return $route->getConfig();
        }

        foreach ($this->storeApiSeoUrlRoutes as $storeApiSeoUrlRoute) {
            $config = $storeApiSeoUrlRoute->getConfig();

            if ($config->getDefinition()->getEntityName() === $entityName) {
                return $config;
            }
        }

        throw SeoUrlRouteConfigException::routeConfigNotFoundForEntityName($entityName);
    }
}
