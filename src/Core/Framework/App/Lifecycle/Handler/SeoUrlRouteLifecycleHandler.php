<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Lifecycle\Handler;

use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\App\Aggregate\AppSeoUrlRoute\AppSeoUrlRouteCollection;
use Shopware\Core\Framework\App\Aggregate\AppSeoUrlRoute\AppSeoUrlRouteDefinition;
use Shopware\Core\Framework\App\Lifecycle\Context\AppActivationContext;
use Shopware\Core\Framework\App\Lifecycle\Context\AppPersistContext;
use Shopware\Core\Framework\App\Lifecycle\Context\AppRemovalContext;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal only for use by the app-system
 */
#[Package('framework')]
class SeoUrlRouteLifecycleHandler extends AbstractLifecycleHandler
{
    /**
     * @param EntityRepository<AppSeoUrlRouteCollection> $seoUrlRouteRepository
     */
    public function __construct(
        private readonly EntityRepository $seoUrlRouteRepository,
        private readonly Connection $connection,
        private readonly ClockInterface $clock,
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
        $now = $this->now();

        foreach ($this->getRouteNames($context->app->getId(), $context->context) as $routeName) {
            $this->connection->update(
                'seo_url',
                ['is_deleted' => 1, 'updated_at' => $now],
                ['route_name' => $routeName]
            );
        }
    }

    public function uninstall(AppRemovalContext $context): void
    {
        $this->removeSeoUrls($this->getRouteNames($context->app->getId(), $context->context));
    }

    public function delete(AppRemovalContext $context): void
    {
        $this->removeSeoUrls($this->getRouteNames($context->app->getId(), $context->context));
    }

    private function persist(AppPersistContext $context): void
    {
        $obsolete = $this->fetchRoutes($context->app->getId(), $context->context);
        $seoUrls = $context->manifest->getStorefront()?->getSeoUrls() ?? [];

        $upserts = [];
        $defaultTemplates = [];

        foreach ($seoUrls as $seoUrl) {
            $payload = $seoUrl->toArray($context->defaultLocale);
            $payload['appId'] = $context->app->getId();
            $payload['routeName'] = AppSeoUrlRouteDefinition::buildRouteName($context->app->getName(), $seoUrl->getName());

            $existing = $obsolete->filterByProperty('name', $seoUrl->getName())->first();
            if ($existing !== null) {
                $payload['id'] = $existing->getId();
                $obsolete->remove($existing->getId());
            }

            $upserts[] = $payload;

            $entityName = $seoUrl->getEntity();
            $defaultTemplate = $seoUrl->getDefaultTemplate();

            if ($entityName !== null && $defaultTemplate !== null) {
                $defaultTemplates[] = [
                    'routeName' => $payload['routeName'],
                    'entityName' => $entityName,
                    'template' => $defaultTemplate,
                    'previousTemplate' => $existing?->getDefaultTemplate(),
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
                $defaultTemplate['previousTemplate']
            );
        }
    }

    private function removeObsolete(AppSeoUrlRouteCollection $obsolete, Context $context): void
    {
        if ($obsolete->count() === 0) {
            return;
        }

        $this->seoUrlRouteRepository->delete(
            array_values(array_map(static fn (string $id): array => ['id' => $id], $obsolete->getIds())),
            $context
        );

        $this->removeSeoUrls(array_values($obsolete->map(static fn ($route): string => $route->getRouteName())));
    }

    /**
     * @param list<string> $routeNames
     */
    private function removeSeoUrls(array $routeNames): void
    {
        foreach ($routeNames as $routeName) {
            $this->connection->delete('seo_url', ['route_name' => $routeName]);
            $this->connection->delete('seo_url_template', ['route_name' => $routeName]);
        }
    }

    private function syncDefaultTemplate(
        string $routeName,
        string $entityName,
        string $template,
        ?string $previousTemplate
    ): void {
        $existing = $this->connection->fetchAssociative(
            'SELECT LOWER(HEX(`id`)) AS `id`, `entity_name` AS `entityName`, `template`
             FROM `seo_url_template`
             WHERE `route_name` = :routeName AND `sales_channel_id` IS NULL',
            ['routeName' => $routeName]
        );

        $now = $this->now();

        if ($existing === false) {
            $this->connection->insert('seo_url_template', [
                'id' => Uuid::randomBytes(),
                'sales_channel_id' => null,
                'route_name' => $routeName,
                'entity_name' => $entityName,
                'template' => $template,
                'is_valid' => 1,
                'is_headless' => 0,
                'created_at' => $now,
            ]);

            return;
        }

        $update = [];

        if ($existing['entityName'] !== $entityName) {
            $update['entity_name'] = $entityName;
        }

        $storedTemplate = $existing['template'];
        if ($previousTemplate !== null && $storedTemplate === $previousTemplate && $storedTemplate !== $template) {
            $update['template'] = $template;
        }

        if ($update === []) {
            return;
        }

        $update['updated_at'] = $now;

        $this->connection->update('seo_url_template', $update, ['id' => Uuid::fromHexToBytes((string) $existing['id'])]);
    }

    /**
     * @return list<string>
     */
    private function getRouteNames(string $appId, Context $context): array
    {
        return array_values($this->fetchRoutes($appId, $context)->map(static fn ($route): string => $route->getRouteName()));
    }

    private function now(): string
    {
        return $this->clock->now()->format(Defaults::STORAGE_DATE_TIME_FORMAT);
    }

    private function fetchRoutes(string $appId, Context $context): AppSeoUrlRouteCollection
    {
        $criteria = new Criteria();
        $criteria->setTitle('app-seo-url-routes::lifecycle');
        $criteria->addFilter(new EqualsFilter('appId', $appId));

        return $this->seoUrlRouteRepository->search($criteria, $context)->getEntities();
    }
}
