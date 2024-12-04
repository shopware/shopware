<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Lifecycle\Persister;

use Shopware\Core\Framework\Adapter\Cache\CacheClearer;
use Shopware\Core\Framework\App\AppCollection;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\App\Template\AbstractTemplateLoader;
use Shopware\Core\Framework\App\Template\TemplateCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Hasher;

/**
 * @internal only for use by the app-system
 */
#[Package('core')]
class TemplatePersister
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

    public function updateTemplates(Manifest $manifest, string $appId, Context $context): void
    {
        $app = $this->getAppWithExistingTemplates($appId, $context);
        $existingTemplates = $app->getTemplates();
        if ($existingTemplates === null) {
            throw AppException::notFound('templates'); // TODO use proper exception
        }
        $templatePaths = $this->templateLoader->getTemplatePathsForApp($manifest);

        $upserts = [];
        $needsCacheClear = false;

        foreach ($templatePaths as $templatePath) {
            $templateContent = $this->templateLoader->getTemplateContent($templatePath, $manifest);

            $existing = $existingTemplates->filterByProperty('path', $templatePath)->first();
            if (!$existing) {
                $upserts[] = [
                    'template' => $templateContent,
                    'path' => $templatePath,
                    'active' => $app->isActive(),
                    'appId' => $appId,
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

        if (!empty($upserts)) {
            $needsCacheClear = true;
            $this->templateRepository->upsert($upserts, $context);
        }

        $ids = $existingTemplates->getIds();
        if (!empty($ids)) {
            $needsCacheClear = true;
            $ids = array_map(static fn (string $id): array => ['id' => $id], array_values($ids));

            $this->templateRepository->delete($ids, $context);
        }

        if ($needsCacheClear) {
            // TODO use `clearHttpCache` method once https://gitlab.shopware.com/shopware/6/product/platform/-/merge_requests/15477 is merged
            $this->cacheClearer->clear();
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
}
