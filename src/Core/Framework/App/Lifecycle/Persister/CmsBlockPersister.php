<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Lifecycle\Persister;

use Shopware\Core\Framework\App\Aggregate\CmsBlock\AppCmsBlockCollection;
use Shopware\Core\Framework\App\Cms\AbstractBlockTemplateLoader;
use Shopware\Core\Framework\App\Cms\CmsExtensions;
use Shopware\Core\Framework\App\Lifecycle\AppLifecycleContext;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal only for use by the app-system
 */
#[Package('framework')]
class CmsBlockPersister implements PersisterInterface
{
    /**
     * @param EntityRepository<AppCmsBlockCollection> $cmsBlockRepository
     */
    public function __construct(
        private readonly EntityRepository $cmsBlockRepository,
        private readonly AbstractBlockTemplateLoader $blockTemplateLoader,
    ) {
    }

    public function persist(AppLifecycleContext $context): void
    {
        if (!$context->appFilesystem->has('Resources/cms.xml')) {
            return;
        }

        $cmsExtensions = CmsExtensions::createFromXmlFile($context->appFilesystem->path('Resources/cms.xml'));

        $existingCmsBlocks = $this->getExistingCmsBlocks($context->app->getId(), $context->context);

        $cmsBlocks = $cmsExtensions->getBlocks() !== null ? $cmsExtensions->getBlocks()->getBlocks() : [];
        $upserts = [];
        foreach ($cmsBlocks as $cmsBlock) {
            $payload = $cmsBlock->toEntityArray($context->app->getId(), $context->defaultLocale);

            $payload['template'] = $this->blockTemplateLoader->getTemplateForBlock($cmsExtensions, $cmsBlock->getName());
            $payload['styles'] = $this->blockTemplateLoader->getStylesForBlock($cmsExtensions, $cmsBlock->getName());

            $existing = $existingCmsBlocks->filterByProperty('name', $cmsBlock->getName())->first();
            if ($existing) {
                $payload['id'] = $existing->getId();
                $existingCmsBlocks->remove($existing->getId());
            }

            $upserts[] = $payload;
        }

        if ($upserts !== []) {
            $this->cmsBlockRepository->upsert($upserts, $context->context);
        }

        $this->deleteOldCmsBlocks($existingCmsBlocks, $context->context);
    }

    private function deleteOldCmsBlocks(AppCmsBlockCollection $toBeRemoved, Context $context): void
    {
        $ids = $toBeRemoved->getIds();

        if ($ids !== []) {
            $ids = array_map(static fn (string $id): array => ['id' => $id], array_values($ids));

            $this->cmsBlockRepository->delete($ids, $context);
        }
    }

    private function getExistingCmsBlocks(string $appId, Context $context): AppCmsBlockCollection
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('appId', $appId));

        return $this->cmsBlockRepository->search($criteria, $context)->getEntities();
    }
}
