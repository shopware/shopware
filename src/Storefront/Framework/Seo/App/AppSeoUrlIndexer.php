<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Seo\App;

use Shopware\Core\Content\Seo\SeoUrlTemplate\SeoUrlTemplateIndexingMessage;
use Shopware\Core\Content\Seo\SeoUrlUpdater;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexer;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexingMessage;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Symfony\Component\Messenger\MessageBusInterface;

use function Symfony\Component\String\u;

/**
 * Keeps the SEO URLs of app entity SEO URL routes in line with the entities they are generated for. A full index
 * run rebuilds each route through the batched {@see SeoUrlTemplateIndexingMessage} chain.
 *
 * @internal
 */
#[Package('inventory')]
class AppSeoUrlIndexer extends EntityIndexer
{
    final public const NAME = 'app_seo_url.indexer';

    final public const SEO_URL_UPDATER = 'app_seo_url.seo-url';

    public function __construct(
        private readonly AppSeoUrlRouteProvider $routes,
        private readonly SeoUrlUpdater $seoUrlUpdater,
        private readonly MessageBusInterface $messageBus,
    ) {
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function iterate(?array $offset): ?EntityIndexingMessage
    {
        $position = $offset['offset'] ?? 0;
        $seoUrl = $this->routes->getEntityRoutes()[$position] ?? null;

        if ($seoUrl === null) {
            return null;
        }

        return new EntityIndexingMessage([$seoUrl->getRouteName()], ['offset' => $position + 1]);
    }

    public function update(EntityWrittenContainerEvent $event): ?EntityIndexingMessage
    {
        $idsByEntity = [];

        foreach ($this->routes->getEntityRoutes() as $seoUrl) {
            $entityName = $seoUrl->getEntityName();

            if (isset($idsByEntity[$entityName])) {
                continue;
            }

            $ids = $this->collectIds($event, $entityName);

            if ($ids !== []) {
                $idsByEntity[$entityName] = $ids;
            }
        }

        if ($idsByEntity === []) {
            return null;
        }

        $message = new AppSeoUrlIndexingMessage(array_merge(...array_values($idsByEntity)), null, $event->getContext());
        $message->setIdsByEntity($idsByEntity);

        return $message;
    }

    public function handle(EntityIndexingMessage $message): void
    {
        if (!$message->allow(self::SEO_URL_UPDATER)) {
            return;
        }

        if ($message instanceof AppSeoUrlIndexingMessage) {
            $this->updateSeoUrls($message);

            return;
        }

        $routeNames = $message->getData();

        if (\is_array($routeNames)) {
            $this->rebuildRoutes($routeNames);
        }
    }

    public function getOptions(): array
    {
        return [self::SEO_URL_UPDATER];
    }

    public function getTotal(): int
    {
        return \count($this->routes->getEntityRoutes());
    }

    public function getDecorated(): EntityIndexer
    {
        throw new DecorationPatternException(static::class);
    }

    private function updateSeoUrls(AppSeoUrlIndexingMessage $message): void
    {
        foreach ($this->routes->getEntityRoutes() as $seoUrl) {
            $ids = $message->getIds($seoUrl->getEntityName());

            if ($ids !== []) {
                $this->seoUrlUpdater->update($seoUrl->getRouteName(), $ids);
            }
        }
    }

    /**
     * @param array<string> $routeNames
     */
    private function rebuildRoutes(array $routeNames): void
    {
        foreach ($this->routes->getEntityRoutes() as $seoUrl) {
            if (\in_array($seoUrl->getRouteName(), $routeNames, true)) {
                $this->messageBus->dispatch(new SeoUrlTemplateIndexingMessage($seoUrl->getRouteName(), $seoUrl->getEntityName()));
            }
        }
    }

    /**
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
