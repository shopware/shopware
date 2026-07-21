<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Lifecycle\Handler;

use Shopware\Core\Framework\Adapter\Cache\CacheClearer;
use Shopware\Core\Framework\App\AppCollection;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\App\Lifecycle\Context\AppActivationContext;
use Shopware\Core\Framework\App\Lifecycle\Context\AppPersistContext;
use Shopware\Core\Framework\App\Template\AbstractTemplateLoader;
use Shopware\Core\Framework\App\Template\TemplateCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Hasher;

/**
 * @internal only for use by the app-system
 */
#[Package('framework')]
class TemplateLifecycleHandler extends AbstractLifecycleHandler
{
    /**
     * @param EntityRepository<TemplateCollection> $templateRepository
     * @param EntityRepository<AppCollection> $appRepository
     */
    public function __construct(
        private readonly AbstractTemplateLoader $templateLoader,
        private readonly EntityRepository $templateRepository,
        private readonly EntityRepository $appRepository,
        private readonly CacheClearer $cacheClearer,
    ) {
    }

    public function install(AppPersistContext $context): void
    {
        // on install the cache is cleared when the templates are activated, see self::updateActiveState()
        $this->persist($context, clearCacheAfterChange: false);
    }

    public function update(AppPersistContext $context): void
    {
        $this->persist($context, clearCacheAfterChange: true);
    }

    public function activate(AppActivationContext $context): void
    {
        $this->updateActiveState($context->app->getId(), $context->context, false, true);
    }

    public function deactivate(AppActivationContext $context): void
    {
        $this->updateActiveState($context->app->getId(), $context->context, true, false);
    }

    private function persist(AppPersistContext $context, bool $clearCacheAfterChange): void
    {
        $app = $this->getAppWithExistingTemplates($context->app->getId(), $context->context);
        $existingTemplates = $app->getTemplates();

        \assert($existingTemplates !== null);

        $templatePaths = $this->templateLoader->getTemplatePathsForApp($context->manifest);

        $upserts = [];

        foreach ($templatePaths as $templatePath) {
            $templateContent = $this->templateLoader->getTemplateContent($templatePath, $context->manifest);

            $existing = $existingTemplates->filterByProperty('path', $templatePath)->first();
            if (!$existing) {
                $upserts[] = [
                    'template' => $templateContent,
                    'path' => $templatePath,
                    'active' => $app->isActive(),
                    'appId' => $context->app->getId(),
                    'hash' => Hasher::hash($templateContent),
                ];

                continue;
            }

            $existingTemplates->remove($existing->getId());

            if (Hasher::hash($templateContent) === $existing->getHash()) {
                continue;
            }

            $upserts[] = [
                'id' => $existing->getId(),
                'template' => $templateContent,
                'hash' => Hasher::hash($templateContent),
            ];
        }
        $needsCacheClear = false;

        if ($upserts !== []) {
            $needsCacheClear = true;
            $this->templateRepository->upsert($upserts, $context->context);
        }

        $ids = $existingTemplates->getIds();
        if ($ids !== []) {
            $needsCacheClear = true;
            $ids = array_map(static fn (string $id): array => ['id' => $id], array_values($ids));

            $this->templateRepository->delete($ids, $context->context);
        }

        if ($needsCacheClear && $clearCacheAfterChange) {
            $this->cacheClearer->clearHttpCache();
        }
    }

    private function getAppWithExistingTemplates(string $appId, Context $context): AppEntity
    {
        $criteria = new Criteria([$appId]);
        $criteria->addAssociation('templates');

        $app = $this->appRepository->search($criteria, $context)->getEntities()->first();
        if ($app === null) {
            throw AppException::notFoundByField($appId, 'id');
        }

        return $app;
    }

    private function updateActiveState(string $appId, Context $context, bool $currentActiveState, bool $newActiveState): void
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('appId', $appId));
        $criteria->addFilter(new EqualsFilter('active', $currentActiveState));

        $templates = $this->templateRepository->searchIds($criteria, $context)->getIds();

        $updateSet = array_map(static fn (string $id) => ['id' => $id, 'active' => $newActiveState], $templates);

        $this->templateRepository->update($updateSet, $context);

        $this->cacheClearer->clearHttpCache();
    }
}
