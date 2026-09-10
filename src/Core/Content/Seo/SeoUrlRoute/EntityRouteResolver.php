<?php declare(strict_types=1);

namespace Shopware\Core\Content\Seo\SeoUrlRoute;

use Shopware\Core\Content\Seo\Exception\SeoUrlRouteConfigException;
use Shopware\Core\Content\Seo\SeoUrlPlaceholderHandlerInterface;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Routing\RouterInterface;

/**
 * @internal
 */
#[Package('inventory')]
class EntityRouteResolver
{
    /**
     * @param iterable<EntitySeoUrlRouteInterface> $storeApiSeoUrlRoutes
     */
    public function __construct(
        private readonly SeoUrlRouteRegistry $registry,
        private readonly SeoUrlPlaceholderHandlerInterface $seoUrlPlaceholderHandler,
        private readonly RouterInterface $router,
        private readonly iterable $storeApiSeoUrlRoutes = [],
    ) {
    }

    public function getRouteNameForEntityName(string $entityName, ?string $salesChannelTypeId = null): string
    {
        return $this->getRouteConfig($entityName, $salesChannelTypeId)->getRouteName();
    }

    /**
     * Generates a SEO URL placeholder for the given entity.
     * Returns store-api route when no route is registered for the entity type (e.g. headless setups).
     */
    public function generateSeoUrlPlaceholder(string $entityName, string $primaryKey, ?string $salesChannelTypeId = null): string
    {
        $config = $this->getRouteConfig($entityName, $salesChannelTypeId);

        return $this->seoUrlPlaceholderHandler->generate($config->getRouteName(), $config->getPrimaryKeyParameter($primaryKey));
    }

    /**
     * Generates a concrete URL for the given entity via the Symfony router.
     * Returns store-api route when no route is registered for the entity type (e.g. headless setups).
     */
    public function generateUrl(string $entityName, string $primaryKey, ?string $salesChannelTypeId = null): string
    {
        $config = $this->getRouteConfig($entityName, $salesChannelTypeId);

        return $this->router->generate($config->getRouteName(), $config->getPrimaryKeyParameter($primaryKey));
    }

    public function findEntitySeoUrlRoute(string $routeName): ?EntitySeoUrlRouteInterface
    {
        foreach ($this->storeApiSeoUrlRoutes as $entitySeoUrlRoute) {
            if ($entitySeoUrlRoute->getConfig()->getRouteName() === $routeName) {
                return $entitySeoUrlRoute;
            }
        }

        return null;
    }

    /**
     * @return array{routeName?: string, pathInfo?: string}
     */
    public function getSeoUrlRouteNameAndPathInfo(
        string $entityName,
        string $routeName,
        string $primaryKey,
        string $salesChannelTypeId
    ): array {
        try {
            $routeNameByEntity = $this->getRouteNameForEntityName(
                $entityName,
                $salesChannelTypeId
            );
        } catch (SeoUrlRouteConfigException) {
            return [];
        }

        if ($routeNameByEntity === $routeName) {
            return [];
        }

        return [
            'routeName' => $routeNameByEntity,
            'pathInfo' => $this->generatePathInfo(
                $entityName,
                $primaryKey,
                $salesChannelTypeId
            ),
        ];
    }

    private function generatePathInfo(string $entityName, string $primaryKey, string $salesChannelTypeId): string
    {
        $url = $this->generateUrl($entityName, $primaryKey, $salesChannelTypeId);
        $baseUrl = $this->router->getContext()->getBaseUrl();

        if ($baseUrl !== '') {
            $url = mb_substr($url, mb_strlen($baseUrl));
        }

        return $url;
    }

    private function getRouteConfig(string $entityName, ?string $salesChannelTypeId = null): SeoUrlRouteConfig
    {
        if ($salesChannelTypeId === Defaults::SALES_CHANNEL_TYPE_API) {
            try {
                return $this->getEntitySeoUrlRouteConfig($entityName);
            } catch (SeoUrlRouteConfigException) {
            }
        }

        $route = array_first($this->registry->findByDefinition($entityName));

        if ($route instanceof EntitySeoUrlRouteInterface) {
            return $route->getConfig();
        }

        return $this->getEntitySeoUrlRouteConfig($entityName);
    }

    private function getEntitySeoUrlRouteConfig(string $entityName): SeoUrlRouteConfig
    {
        foreach ($this->storeApiSeoUrlRoutes as $storeApiSeoUrlRoute) {
            $config = $storeApiSeoUrlRoute->getConfig();

            if ($config->getDefinition()->getEntityName() === $entityName) {
                return $config;
            }
        }

        throw SeoUrlRouteConfigException::routeConfigNotFoundForEntityName($entityName);
    }
}
