<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Seo\App;

use Shopware\Core\Content\Seo\SeoUrlUpdater;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Storefront\Framework\Seo\App\Message\AppSeoUrlSyncMessage;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\MessageBusInterface;

use function Symfony\Component\String\u;

/**
 * @internal
 */
#[Package('inventory')]
class AppSeoUrlUpdateListener implements EventSubscriberInterface
{
    public function __construct(
        private readonly AppSeoUrlRouteProvider $routes,
        private readonly SeoUrlUpdater $seoUrlUpdater,
        private readonly MessageBusInterface $messageBus,
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
        foreach ($this->routes->getEntityRoutes() as $route) {
            if ($route->entityName === null) {
                continue;
            }

            $ids = $this->collectIds($event, $route->entityName);

            if ($ids === []) {
                continue;
            }

            $this->seoUrlUpdater->update($route->routeName, $ids);
        }
    }

    public function syncStaticSeoUrls(): void
    {
        $this->messageBus->dispatch(new AppSeoUrlSyncMessage());
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
