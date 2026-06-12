<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Lifecycle\Persister;

use Shopware\Core\Framework\Adapter\Cache\CacheClearer;
use Shopware\Core\Framework\App\AppCollection;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\App\Lifecycle\AppLifecycleContext;
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
class TemplatePersister implements PersisterInterface
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

    public function persist(AppLifecycleContext $context): void
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

        /**
         * only clear cache when we are in an update context
         * otherwise cache is cleared on template active/deactivate
         *
         * @see self::updateActiveState()
         **/
        if ($needsCacheClear && !$context->isInstall) {
            $this->cacheClearer->clearHttpCache();
        }
    }

    public function activate(AppEntity $app, Context $context): void
    {
        $this->updateActiveState($app->getId(), $context, false, true);
    }

    public function deactivate(AppEntity $app, Context $context): void
    {
        $this->updateActiveState($app->getId(), $context, true, false);
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
