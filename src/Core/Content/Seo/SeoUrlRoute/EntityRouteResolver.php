<?php declare(strict_types=1);

namespace Shopware\Core\Content\Seo\SeoUrlRoute;

use Shopware\Core\Content\Seo\SeoUrlPlaceholderHandlerInterface;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Routing\RouterInterface;

#[Package('inventory')]
class EntityRouteResolver
{
    /**
     * @internal
     */
    public function __construct(
        private readonly SeoUrlRouteRegistry $registry,
        private readonly SeoUrlPlaceholderHandlerInterface $seoUrlPlaceholderHandler,
        private readonly RouterInterface $router,
    ) {
    }

    public function getRouteNameForEntityName(string $entityName): string
    {
        $routes = $this->registry->findByDefinition($entityName);
        $route = \array_key_exists(0, $routes) ? $routes[0]->getConfig()->getRouteName() : null;

        if ($route) {
            return $route;
        }

        $fallback = \sprintf('store-api.%s.detail', str_replace('_', '-', $entityName));

        return $this->router->getRouteCollection()->get($fallback) !== null ? $fallback : $entityName;
    }

    /**
     * Generates a SEO URL placeholder for the given entity.
     * Returns store-api route when no route is registered for the entity type (e.g. headless setups).
     *
     * @param array<string, mixed> $parameters
     */
    public function generateSeoUrlPlaceholder(string $entityName, array $parameters = []): string
    {
        $routeName = $this->getRouteNameForEntityName($entityName);

        return $this->seoUrlPlaceholderHandler->generate($routeName, $parameters);
    }

    /**
     * Generates a concrete URL for the given entity via the Symfony router.
     * Returns store-api route when no route is registered for the entity type (e.g. headless setups).
     *
     * @param array<string, mixed> $parameters
     */
    public function generateUrl(string $entityName, array $parameters = []): string
    {
        $routeName = $this->getRouteNameForEntityName($entityName);

        return $this->router->generate($routeName, $parameters);
    }
}
