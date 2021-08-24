<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Lifecycle\Persister;

use Shopware\Core\Framework\App\Aggregate\CmsBlock\AppCmsBlockCollection;
use Shopware\Core\Framework\App\Aggregate\CmsBlock\AppCmsBlockEntity;
use Shopware\Core\Framework\App\Cms\AbstractBlockTemplateLoader;
use Shopware\Core\Framework\App\Cms\CmsExtensions;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Util\HtmlSanitizer;

/**
 * @internal
 */
class CmsBlockPersister
{
    private EntityRepositoryInterface $cmsBlockRepository;

    private AbstractBlockTemplateLoader $blockTemplateLoader;

    private HtmlSanitizer $htmlSanitizer;

    public function __construct(
        EntityRepositoryInterface $cmsBlockRepository,
        AbstractBlockTemplateLoader $blockTemplateLoader,
        HtmlSanitizer $htmlSanitizer
    ) {
        $this->cmsBlockRepository = $cmsBlockRepository;
        $this->blockTemplateLoader = $blockTemplateLoader;
        $this->htmlSanitizer = $htmlSanitizer;
    }

    public function updateCmsBlocks(
        CmsExtensions $cmsExtensions,
        string $appId,
        string $defaultLocale,
        Context $context
    ): void {
        $existingCmsBlocks = $this->getExistingCmsBlocks($appId, $context);

        $cmsBlocks = $cmsExtensions->getBlocks() !== null ? $cmsExtensions->getBlocks()->getBlocks() : [];
        $upserts = [];
        foreach ($cmsBlocks as $cmsBlock) {
            $payload = $cmsBlock->toEntityArray($appId, $defaultLocale);

            $template = $this->blockTemplateLoader->getTemplateForBlock($cmsExtensions, $cmsBlock->getName());

            if (!Feature::isActive('FEATURE_NEXT_15172')) {
                $template = $this->htmlSanitizer->sanitize(
                    $template,
                    [],
                    false,
                    'app_cms_block.template'
                );
            }

            $payload['template'] = $template;
            $payload['styles'] = $this->blockTemplateLoader->getStylesForBlock($cmsExtensions, $cmsBlock->getName());

            /** @var AppCmsBlockEntity|null $existing */
            $existing = $existingCmsBlocks->filterByProperty('name', $cmsBlock->getName())->first();
            if ($existing) {
                $payload['id'] = $existing->getId();
                $existingCmsBlocks->remove($existing->getId());
            }

            $upserts[] = $payload;
        }

        if (!empty($upserts)) {
            $this->cmsBlockRepository->upsert($upserts, $context);
        }

        $this->deleteOldCmsBlocks($existingCmsBlocks, $context);
    }

    private function deleteOldCmsBlocks(AppCmsBlockCollection $toBeRemoved, Context $context): void
    {
        /** @var string[] $ids */
        $ids = $toBeRemoved->getIds();

        if (!empty($ids)) {
            $ids = array_map(static function (string $id): array {
                return ['id' => $id];
            }, array_values($ids));

            $this->cmsBlockRepository->delete($ids, $context);
        }
    }

    private function getExistingCmsBlocks(string $appId, Context $context): AppCmsBlockCollection
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('appId', $appId));

        /** @var AppCmsBlockCollection $cmsBlocks */
        $cmsBlocks = $this->cmsBlockRepository->search($criteria, $context)->getEntities();

        return $cmsBlocks;
    }
}
