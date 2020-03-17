<?php declare(strict_types=1);

namespace Shopware\Core\Content\Seo\SeoUrlRoute;

class SeoUrlRouteRegistry
{
    /**
     * @var SeoUrlRouteInterface[]
     */
    private $seoUrlRoutes = [];

    /**
     * @var SeoUrlRouteInterface[]
     */
    private $definitionToRoutes = [];

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
