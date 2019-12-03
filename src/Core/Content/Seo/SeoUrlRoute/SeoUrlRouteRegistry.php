<?php declare(strict_types=1);

namespace Shopware\Core\Content\Seo\SeoUrlRoute;

class SeoUrlRouteRegistry
{
    /**
     * @var SeoUrlRouteInterface[]
     */
    private $seoUrlRoutes = [];

    public function __construct(iterable $seoUrlRoutes)
    {
        /** @var SeoUrlRouteInterface $seoUrlRoute */
        foreach ($seoUrlRoutes as $seoUrlRoute) {
            $route = $seoUrlRoute->getConfig()->getRouteName();
            $this->seoUrlRoutes[$route] = $seoUrlRoute;
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
}
