<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Seo\App;

use Shopware\Core\Content\Seo\SeoUrlUpdater;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

use function Symfony\Component\String\u;

/**
 * @internal
 */
#[Package('inventory')]
class AppSeoUrlUpdateListener implements EventSubscriberInterface
{
    public function __construct(
        private readonly AppSeoUrlRouteLoader $routeLoader,
        private readonly AppStaticSeoUrlSynchronizer $staticSynchronizer,
        private readonly SeoUrlUpdater $seoUrlUpdater,
    ) {
    }

    /**
     * @return array<string, string>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            EntityWrittenContainerEvent::class => 'updateAppSeoUrls',
            'sales_channel_domain.written' => 'syncStaticSeoUrls',
        ];
    }

    /**
     * @param EntityWrittenContainerEvent<string|array<string, string>> $event
     */
    public function updateAppSeoUrls(EntityWrittenContainerEvent $event): void
    {
        $routes = $this->routeLoader->getEntityRoutes();

        if ($routes === []) {
            return;
        }

        foreach ($routes as $route) {
            $ids = $this->collectIds($event, $route['entityName']);

            if ($ids === []) {
                continue;
            }

            $this->seoUrlUpdater->update($route['routeName'], $ids);
        }
    }

    public function syncStaticSeoUrls(): void
    {
        $this->staticSynchronizer->sync();
    }

    /**
     * @param EntityWrittenContainerEvent<string|array<string, string>> $event
     *
     * @return list<string>
     */
    private function collectIds(EntityWrittenContainerEvent $event, string $entityName): array
    {
        $ids = [];

        foreach ($event->getPrimaryKeys($entityName) as $primaryKey) {
            if (\is_string($primaryKey)) {
                $ids[$primaryKey] = true;
            }
        }

        $parentKey = u($entityName)->camel()->toString() . 'Id';

        foreach ($event->getPrimaryKeys($entityName . '_translation') as $primaryKey) {
            if (\is_array($primaryKey) && isset($primaryKey[$parentKey])) {
                $ids[$primaryKey[$parentKey]] = true;
            }
        }

        return array_keys($ids);
    }
}
