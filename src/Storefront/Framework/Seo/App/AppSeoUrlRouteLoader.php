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
        foreach ($this->routes->getEntityRoutes() as $seoUrl) {
            if (!$this->definitionRegistry->has($seoUrl->getEntityName())) {
                continue;
            }

            yield new ConfiguredEntitySeoUrlRoute(new AppSeoUrlRoute(
                $this->definitionRegistry->getByEntityName($seoUrl->getEntityName()),
                $seoUrl,
            ));
        }
    }
}
