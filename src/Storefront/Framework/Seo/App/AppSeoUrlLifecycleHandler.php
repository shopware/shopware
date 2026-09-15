<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Seo\App;

use Shopware\Core\Content\Seo\SeoUrlUpdater;
use Shopware\Core\Framework\App\Lifecycle\Context\AppActivationContext;
use Shopware\Core\Framework\App\Lifecycle\Context\AppPersistContext;
use Shopware\Core\Framework\App\Lifecycle\Handler\AbstractLifecycleHandler;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\RepositoryIterator;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('inventory')]
class AppSeoUrlLifecycleHandler extends AbstractLifecycleHandler
{
    private const CHUNK_SIZE = 500;

    public function __construct(
        private readonly AppSeoUrlRouteLoader $routeLoader,
        private readonly AppStaticSeoUrlSynchronizer $staticSynchronizer,
        private readonly SeoUrlUpdater $seoUrlUpdater,
        private readonly DefinitionInstanceRegistry $definitionRegistry,
    ) {
    }

    public function activate(AppActivationContext $context): void
    {
        $this->generate($context->app->getId());
    }

    public function update(AppPersistContext $context): void
    {
        if (!$context->app->isActive()) {
            return;
        }

        $this->generate($context->app->getId());
    }

    private function generate(string $appId): void
    {
        $this->routeLoader->invalidateCache();

        $this->staticSynchronizer->sync($appId);

        foreach ($this->routeLoader->getEntityRoutes($appId) as $route) {
            if (!$this->definitionRegistry->has($route['entityName'])) {
                continue;
            }

            $criteria = new Criteria();
            $criteria->setTitle('app-seo-url-routes::generate');
            $criteria->setLimit(self::CHUNK_SIZE);

            $iterator = new RepositoryIterator(
                $this->definitionRegistry->getRepository($route['entityName']),
                Context::createDefaultContext(),
                $criteria
            );

            while (($ids = $iterator->fetchIds()) !== null) {
                $ids = array_values(array_filter($ids, 'is_string'));

                if ($ids === []) {
                    continue;
                }

                $this->seoUrlUpdater->update($route['routeName'], $ids);
            }
        }
    }
}
