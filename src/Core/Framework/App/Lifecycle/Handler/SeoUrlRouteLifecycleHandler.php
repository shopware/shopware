<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Lifecycle\Handler;

use Shopware\Core\Content\Seo\SeoUrl\SeoUrlCollection;
use Shopware\Core\Content\Seo\SeoUrlTemplate\SeoUrlTemplateCollection;
use Shopware\Core\Framework\App\Aggregate\AppSeoUrlRoute\AppSeoUrlRouteEntity;
use Shopware\Core\Framework\App\Lifecycle\Context\AppActivationContext;
use Shopware\Core\Framework\App\Lifecycle\Context\AppPersistContext;
use Shopware\Core\Framework\App\Lifecycle\Context\AppRemovalContext;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal only for use by the app-system
 */
#[Package('framework')]
class SeoUrlRouteLifecycleHandler extends AbstractLifecycleHandler
{
    private const WRITE_CHUNK_SIZE = 500;

    /**
     * @param EntityRepository<EntityCollection<AppSeoUrlRouteEntity>> $seoUrlRouteRepository
     * @param EntityRepository<SeoUrlCollection> $seoUrlRepository
     * @param EntityRepository<SeoUrlTemplateCollection> $seoUrlTemplateRepository
     */
    public function __construct(
        private readonly EntityRepository $seoUrlRouteRepository,
        private readonly EntityRepository $seoUrlRepository,
        private readonly EntityRepository $seoUrlTemplateRepository,
    ) {
    }

    public function install(AppPersistContext $context): void
    {
        $this->persist($context);
    }

    public function update(AppPersistContext $context): void
    {
        $this->persist($context);
    }

    public function deactivate(AppActivationContext $context): void
    {
        $routeNames = $this->fetchRouteNames($context->app->getId(), $context->context);

        $this->markSeoUrlsAsDeleted($routeNames, $context->context);
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
        $routeNames = $this->fetchRouteNames($context->app->getId(), $context->context);

        $this->markSeoUrlsAsDeleted($routeNames, $context->context);
        $this->deleteTemplates($routeNames, $context->context);
    }

    private function persist(AppPersistContext $context): void
    {
        $appId = $context->app->getId();
        $obsolete = $this->fetchRoutes($appId, $context->context);
        $seoUrls = $context->manifest->getStorefront()?->getSeoUrls() ?? [];

        $upserts = [];
        $defaultTemplates = [];

        foreach ($seoUrls as $seoUrl) {
            $payload = $seoUrl->toArray($context->defaultLocale);
            $payload['appId'] = $appId;
            $payload['routeName'] = $seoUrl->getRouteName($context->app->getName());

            $existing = $obsolete->filter(static fn (AppSeoUrlRouteEntity $route): bool => $route->name === $seoUrl->getName())->first();
            if ($existing !== null) {
                $payload['id'] = $existing->id;
                $obsolete->remove($existing->id);
            }

            $upserts[] = $payload;

            $entityName = $seoUrl->getEntity();
            $defaultTemplate = $seoUrl->getDefaultTemplate();

            if ($entityName !== null && $defaultTemplate !== null) {
                $defaultTemplates[] = [
                    'routeName' => $payload['routeName'],
                    'entityName' => $entityName,
                    'template' => $defaultTemplate,
                    'previousTemplate' => $existing?->defaultTemplate,
                ];
            }
        }

        if ($upserts !== []) {
            $this->seoUrlRouteRepository->upsert($upserts, $context->context);
        }

        $this->removeObsolete($obsolete, $context->context);

        foreach ($defaultTemplates as $defaultTemplate) {
            $this->syncDefaultTemplate(
                $defaultTemplate['routeName'],
                $defaultTemplate['entityName'],
                $defaultTemplate['template'],
                $defaultTemplate['previousTemplate'],
                $context->context
            );
        }
    }

    /**
     * @param EntityCollection<AppSeoUrlRouteEntity> $obsolete
     */
    private function removeObsolete(EntityCollection $obsolete, Context $context): void
    {
        if ($obsolete->count() === 0) {
            return;
        }

        $this->seoUrlRouteRepository->delete(
            array_values(array_map(static fn (string $id): array => ['id' => $id], $obsolete->getIds())),
            $context
        );

        $routeNames = array_values($obsolete->map(static fn (AppSeoUrlRouteEntity $route): string => $route->routeName));

        $this->markSeoUrlsAsDeleted($routeNames, $context);
        $this->deleteTemplates($routeNames, $context);
    }

    private function syncDefaultTemplate(
        string $routeName,
        string $entityName,
        string $template,
        ?string $previousTemplate,
        Context $context
    ): void {
        $criteria = new Criteria();
        $criteria->setTitle('app-seo-url-routes::default-template');
        $criteria->addFilter(new EqualsFilter('routeName', $routeName));
        $criteria->addFilter(new EqualsFilter('salesChannelId', null));

        $existing = $this->seoUrlTemplateRepository->search($criteria, $context)->getEntities()->first();

        if ($existing === null) {
            $this->seoUrlTemplateRepository->create([[
                'id' => Uuid::randomHex(),
                'salesChannelId' => null,
                'routeName' => $routeName,
                'entityName' => $entityName,
                'template' => $template,
                'isValid' => true,
                'isHeadless' => false,
            ]], $context);

            return;
        }

        $update = [];

        if ($existing->getEntityName() !== $entityName) {
            $update['entityName'] = $entityName;
        }

        if ($previousTemplate !== null && $existing->getTemplate() === $previousTemplate && $previousTemplate !== $template) {
            $update['template'] = $template;
        }

        if ($update === []) {
            return;
        }

        $this->seoUrlTemplateRepository->update([['id' => $existing->getId(), ...$update]], $context);
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
        $criteria->setTitle('app-seo-url-routes::mark-deleted');
        $criteria->addFilter(new EqualsAnyFilter('routeName', $routeNames));
        $criteria->addFilter(new EqualsFilter('isDeleted', false));

        /** @var list<string> $ids */
        $ids = $this->seoUrlRepository->searchIds($criteria, $context)->getIds();

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
        $criteria->setTitle('app-seo-url-routes::delete-templates');
        $criteria->addFilter(new EqualsAnyFilter('routeName', $routeNames));

        /** @var list<string> $ids */
        $ids = $this->seoUrlTemplateRepository->searchIds($criteria, $context)->getIds();

        if ($ids === []) {
            return;
        }

        $this->seoUrlTemplateRepository->delete(
            array_map(static fn (string $id): array => ['id' => $id], $ids),
            $context
        );
    }

    /**
     * @return list<string>
     */
    private function fetchRouteNames(string $appId, Context $context): array
    {
        return array_values($this->fetchRoutes($appId, $context)->map(static fn (AppSeoUrlRouteEntity $route): string => $route->routeName));
    }

    /**
     * @return EntityCollection<AppSeoUrlRouteEntity>
     */
    private function fetchRoutes(string $appId, Context $context): EntityCollection
    {
        $criteria = new Criteria();
        $criteria->setTitle('app-seo-url-routes::lifecycle');
        $criteria->addFilter(new EqualsFilter('appId', $appId));

        return $this->seoUrlRouteRepository->search($criteria, $context)->getEntities();
    }
}
