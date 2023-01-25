<?php declare(strict_types=1);

namespace Shopware\Core\Content\Seo\SeoUrlRoute;

use Shopware\Core\Framework\Log\Package;

#[Package('sales-channel')]
class SeoUrlRouteRegistry
{
    /**
     * @var SeoUrlRouteInterface[]
     */
    private array $seoUrlRoutes = [];

    /**
     * @var SeoUrlRouteInterface[]
     */
    private array $definitionToRoutes = [];

    /**
     * @internal
     */
    public function __construct(iterable $seoUrlRoutes)
    {
        /** @var SeoUrlRouteInterface $seoUrlRoute */
        foreach ($seoUrlRoutes as $seoUrlRoute) {
            $config = $seoUrlRoute->getConfig();

            $route = $config->getRouteName();
            $this->seoUrlRoutes[$route] = $seoUrlRoute;
            $this->definitionToRoutes[$config->getDefinition()->getEntityName()][] = $seoUrlRoute;
        }
    }

    public function getSeoUrlRoutes(): iterable
    {
        return $this->seoUrlRoutes;
    }

    public function findByRouteName(string $routeName): ?SeoUrlRouteInterface
    {
        return $this->seoUrlRoutes[$routeName] ?? null;
    }

    /**
     * @return SeoUrlRouteInterface[]
     */
    public function findByDefinition(string $definitionName): array
    {
        return $this->definitionToRoutes[$definitionName] ?? [];
    }
}
