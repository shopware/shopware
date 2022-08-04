<?php declare(strict_types=1);

namespace Shopware\Core\Content\Cms\SalesChannel;

use Shopware\Core\Content\Cms\Aggregate\CmsBlock\CmsBlockEntity;
use Shopware\Core\Content\Cms\Aggregate\CmsSection\CmsSectionEntity;
use Shopware\Core\Content\Cms\CmsPageCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Feature;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @deprecated tag:v6.5.0 - SalesChannelCmsPageRepository will be removed.
 */
class SalesChannelCmsPageRepository
{
    private EntityRepositoryInterface $cmsPageRepository;

    /**
     * @internal
     */
    public function __construct(EntityRepositoryInterface $repository)
    {
        $this->cmsPageRepository = $repository;
    }

    public function read(array $ids, SalesChannelContext $context): CmsPageCollection
    {
        Feature::triggerDeprecationOrThrow(
            'v6.5.0.0',
            Feature::deprecatedClassMessage(__CLASS__, 'v6.5.0.0')
        );

        $criteria = new Criteria($ids);

        return $this->readCmsPages($criteria, $context);
    }

    public function getPagesByType(string $type, SalesChannelContext $context): CmsPageCollection
    {
        Feature::triggerDeprecationOrThrow(
            'v6.5.0.0',
            Feature::deprecatedClassMessage(__CLASS__, 'v6.5.0.0')
        );

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('cms_page.type', $type));

        return $this->readCmsPages($criteria, $context);
    }

    private function readCmsPages(Criteria $criteria, SalesChannelContext $context): CmsPageCollection
    {
        Feature::triggerDeprecationOrThrow(
            'v6.5.0.0',
            Feature::deprecatedClassMessage(__CLASS__, 'v6.5.0.0')
        );

        $criteria->addAssociation('sections.backgroundMedia')
            ->addAssociation('sections.blocks.backgroundMedia')
            ->addAssociation('sections.blocks.slots');

        /** @var CmsPageCollection $pages */
        $pages = $this->cmsPageRepository->search($criteria, $context->getContext())->getEntities();

        foreach ($pages as $page) {
            if ($page->getSections() === null) {
                continue;
            }

            $page->getSections()->sort(function (CmsSectionEntity $a, CmsSectionEntity $b) {
                return $a->getPosition() <=> $b->getPosition();
            });

            foreach ($page->getSections() as $section) {
                if ($section->getBlocks() === null) {
                    continue;
                }

                $section->getBlocks()->sort(function (CmsBlockEntity $a, CmsBlockEntity $b) {
                    return $a->getPosition() <=> $b->getPosition();
                });
            }
        }

        return $pages;
    }
}
