<?php declare(strict_types=1);

namespace Shopware\Core\Content\Seo\SeoUrlRoute;

use Shopware\Core\Content\Seo\SeoUrlPlaceholderHandlerInterface;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
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

    public function getRouteNameForEntityName(
        string $entityName,
        ArrayStruct $parameters = new ArrayStruct(),
        ?SalesChannelEntity $salesChannel = null
    ): string {
        $routes = $this->registry->findByDefinition($entityName);
        $key = array_key_first($routes);
        $route = $key !== null ? $routes[$key] : null;
        $config = $route?->getConfig();

        if ($config) {
            return $salesChannel
                ? $config->getRouteBySalesChannel($salesChannel, $parameters)
                : $config->getRouteName();
        }

        return \sprintf('store-api.%s.detail', str_replace('_', '-', $entityName));
    }

    /**
     * Generates a SEO URL placeholder for the given entity.
     * Returns store-api route when no route is registered for the entity type (e.g. headless setups).
     */
    public function generateSeoUrlPlaceholder(
        string $entityName,
        ArrayStruct $parameters = new ArrayStruct(),
        ?SalesChannelEntity $salesChannel = null,
    ): string {
        $routeName = $this->getRouteNameForEntityName($entityName, $parameters, $salesChannel);

        return $this->seoUrlPlaceholderHandler->generate($routeName, $parameters->getVars());
    }

    /**
     * Generates a concrete URL for the given entity via the Symfony router.
     * Returns store-api route when no route is registered for the entity type (e.g. headless setups).
     */
    public function generateUrl(
        string $entityName,
        ArrayStruct $parameters = new ArrayStruct(),
        ?SalesChannelEntity $salesChannel = null,
    ): string {
        $routeName = $this->getRouteNameForEntityName($entityName, $parameters, $salesChannel);

        return $this->router->generate($routeName, $parameters->getVars());
    }
}
