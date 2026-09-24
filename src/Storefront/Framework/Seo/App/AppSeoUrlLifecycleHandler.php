<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Seo\App;

use Shopware\Core\Content\Seo\SeoUrl\SeoUrlCollection;
use Shopware\Core\Content\Seo\SeoUrlTemplate\SeoUrlTemplateCollection;
use Shopware\Core\Content\Seo\SeoUrlTemplate\SeoUrlTemplateEntity;
use Shopware\Core\Content\Seo\SeoUrlTemplate\SeoUrlTemplateIndexingMessage;
use Shopware\Core\Framework\App\Event\AppUpdatedEvent;
use Shopware\Core\Framework\App\Feature\AppFeatureStorage;
use Shopware\Core\Framework\App\Lifecycle\Context\AppActivationContext;
use Shopware\Core\Framework\App\Lifecycle\Context\AppPersistContext;
use Shopware\Core\Framework\App\Lifecycle\Context\AppRemovalContext;
use Shopware\Core\Framework\App\Lifecycle\Handler\AbstractLifecycleHandler;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\App\Manifest\Xml\Storefront\SeoUrl;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\PrefixFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Storefront\Framework\Seo\App\Message\AppSeoUrlSyncMessage;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * @internal
 */
#[Package('inventory')]
class AppSeoUrlLifecycleHandler extends AbstractLifecycleHandler implements EventSubscriberInterface
{
    private const WRITE_CHUNK_SIZE = 500;

    /**
     * @param EntityRepository<SeoUrlCollection> $seoUrlRepository
     * @param EntityRepository<SeoUrlTemplateCollection> $seoUrlTemplateRepository
     */
    public function __construct(
        private readonly AppFeatureStorage $storage,
        private readonly EntityRepository $seoUrlRepository,
        private readonly EntityRepository $seoUrlTemplateRepository,
        private readonly MessageBusInterface $messageBus,
    ) {
    }

    /**
     * @return array<string, string>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            AppUpdatedEvent::class => 'regenerateUpdatedApp',
        ];
    }

    public function install(AppPersistContext $context): void
    {
        $routeNamePrefix = AppSeoUrlRoute::routeNamePrefix($context->app->getName());
        $declared = $this->declaredEntityRoutes($context->manifest);

        $keptRouteNames = array_filter(
            $this->templateRouteNames($routeNamePrefix, $context->context),
            static fn (string $routeName): bool => !str_contains(substr($routeName, \strlen($routeNamePrefix)), '.')
        );

        $this->deleteTemplates(array_values(array_diff($keptRouteNames, array_keys($declared))), $context->context);
    }

    public function update(AppPersistContext $context): void
    {
        $declaredStatic = $this->declaredStaticRouteNames($context->manifest);
        $declaredEntity = $this->declaredEntityRoutes($context->manifest);

        $removed = [];

        foreach ($this->storage->forApp($context->app->getId(), AppSeoUrlConfig::class) as $feature) {
            if (!\in_array($feature->config->getRouteName(), $declaredStatic, true)) {
                $removed[] = $feature->config->getRouteName();
            }
        }

        foreach ($this->storedEntityRoutes($context->app->getId()) as $seoUrl) {
            if (($declaredEntity[$seoUrl->getRouteName()] ?? null) !== $seoUrl->getEntityName()) {
                $removed[] = $seoUrl->getRouteName();
            }
        }

        $this->markSeoUrlsAsDeleted($removed, $context->context);
        $this->deleteTemplates(array_values(array_diff($removed, array_keys($declaredEntity))), $context->context);
    }

    public function activate(AppActivationContext $context): void
    {
        $this->regenerate($context->app->getId());
    }

    public function regenerateUpdatedApp(AppUpdatedEvent $event): void
    {
        if ($event->getApp()->isActive()) {
            $this->regenerate($event->getApp()->getId());
        }
    }

    public function deactivate(AppActivationContext $context): void
    {
        $this->markSeoUrlsAsDeleted($this->storedRouteNames($context->app->getId()), $context->context);
    }

    public function uninstall(AppRemovalContext $context): void
    {
        $this->remove($context);
    }

    public function delete(AppRemovalContext $context): void
    {
        $this->remove($context);
    }

    private function remove(AppRemovalContext $context): void
    {
        $appId = $context->app->getId();

        $this->markSeoUrlsAsDeleted($this->storedRouteNames($appId), $context->context);

        if (!$context->keepUserData) {
            $this->deleteTemplates(
                array_map(static fn (AppEntitySeoUrlConfig $seoUrl): string => $seoUrl->getRouteName(), $this->storedEntityRoutes($appId)),
                $context->context
            );
        }
    }

    private function regenerate(string $appId): void
    {
        $this->messageBus->dispatch(new AppSeoUrlSyncMessage($appId));

        foreach ($this->storedEntityRoutes($appId) as $seoUrl) {
            $this->messageBus->dispatch(new SeoUrlTemplateIndexingMessage($seoUrl->getRouteName(), $seoUrl->getEntityName()));
        }
    }

    /**
     * @return list<string>
     */
    private function declaredStaticRouteNames(Manifest $manifest): array
    {
        $appName = $manifest->getMetadata()->getName();

        return array_map(
            static fn (SeoUrl $seoUrl): string => AppSeoUrlRoute::buildRouteName($appName, $seoUrl->getName()),
            $manifest->getStorefront()?->getSeoUrls() ?? []
        );
    }

    /**
     * @return array<string, string> route name => entity name
     */
    private function declaredEntityRoutes(Manifest $manifest): array
    {
        $appName = $manifest->getMetadata()->getName();
        $routes = [];

        foreach ($manifest->getStorefront()?->getEntitySeoUrls() ?? [] as $seoUrl) {
            $routes[AppSeoUrlRoute::buildRouteName($appName, $seoUrl->getName())] = $seoUrl->getEntity();
        }

        return $routes;
    }

    /**
     * @return list<AppEntitySeoUrlConfig>
     */
    private function storedEntityRoutes(string $appId): array
    {
        $routes = [];

        foreach ($this->storage->forApp($appId, AppEntitySeoUrlConfig::class) as $feature) {
            $routes[] = $feature->config;
        }

        return $routes;
    }

    /**
     * @return list<string>
     */
    private function storedRouteNames(string $appId): array
    {
        $routeNames = [];

        foreach ($this->storage->forApp($appId, AppSeoUrlConfig::class) as $feature) {
            $routeNames[] = $feature->config->getRouteName();
        }

        foreach ($this->storedEntityRoutes($appId) as $seoUrl) {
            $routeNames[] = $seoUrl->getRouteName();
        }

        return $routeNames;
    }

    /**
     * @return list<string>
     */
    private function templateRouteNames(string $routeNamePrefix, Context $context): array
    {
        $criteria = new Criteria();
        $criteria->setTitle('app-seo-url::kept-templates');
        $criteria->addFilter(new PrefixFilter('routeName', $routeNamePrefix));

        return array_values(array_unique(
            $this->seoUrlTemplateRepository->search($criteria, $context)->getEntities()->map(
                static fn (SeoUrlTemplateEntity $template): string => $template->getRouteName()
            )
        ));
    }

    /**
     * @param list<string> $routeNames
     */
    private function markSeoUrlsAsDeleted(array $routeNames, Context $context): void
    {
        if ($routeNames === []) {
            return;
        }

        $criteria = new Criteria();
        $criteria->setTitle('app-seo-url::mark-deleted');
        $criteria->addFilter(new EqualsAnyFilter('routeName', $routeNames));
        $criteria->addFilter(new EqualsFilter('isDeleted', false));

        $ids = array_values(array_filter($this->seoUrlRepository->searchIds($criteria, $context)->getIds(), \is_string(...)));

        foreach (array_chunk($ids, self::WRITE_CHUNK_SIZE) as $chunk) {
            $this->seoUrlRepository->update(
                array_map(static fn (string $id): array => ['id' => $id, 'isDeleted' => true], $chunk),
                $context
            );
        }
    }

    /**
     * @param list<string> $routeNames
     */
    private function deleteTemplates(array $routeNames, Context $context): void
    {
        if ($routeNames === []) {
            return;
        }

        $criteria = new Criteria();
        $criteria->setTitle('app-seo-url::delete-templates');
        $criteria->addFilter(new EqualsAnyFilter('routeName', $routeNames));

        $ids = array_values(array_filter($this->seoUrlTemplateRepository->searchIds($criteria, $context)->getIds(), \is_string(...)));

        if ($ids === []) {
            return;
        }

        $this->seoUrlTemplateRepository->delete(
            array_map(static fn (string $id): array => ['id' => $id], $ids),
            $context
        );
    }
}
