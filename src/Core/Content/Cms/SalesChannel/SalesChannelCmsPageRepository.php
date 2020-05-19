<?php declare(strict_types=1);

namespace Shopware\Core\Content\Cms\SalesChannel;

use Shopware\Core\Content\Cms\Aggregate\CmsBlock\CmsBlockEntity;
use Shopware\Core\Content\Cms\Aggregate\CmsSection\CmsSectionEntity;
use Shopware\Core\Content\Cms\CmsPageCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class SalesChannelCmsPageRepository
{
    /**
     * @var EntityRepositoryInterface
     */
    private $cmsPageRepository;

    public function __construct(EntityRepositoryInterface $repository)
    {
        $this->cmsPageRepository = $repository;
    }

    public function read(array $ids, SalesChannelContext $context): CmsPageCollection
    {
        $criteria = new Criteria($ids);

        $criteria->addAssociation('sections.backgroundMedia')
            ->addAssociation('sections.blocks.backgroundMedia')
            ->addAssociation('sections.blocks.slots');

        /** @var CmsPageCollection $pages */
        $pages = $this->cmsPageRepository->search($criteria, $context->getContext())->getEntities();

        foreach ($pages as $page) {
            $page->getSections()->sort(function (CmsSectionEntity $a, CmsSectionEntity $b) {
                return $a->getPosition() <=> $b->getPosition();
            });

            foreach ($page->getSections() as $section) {
                $section->getBlocks()->sort(function (CmsBlockEntity $a, CmsBlockEntity $b) {
                    return $a->getPosition() <=> $b->getPosition();
                });
            }
        }

        return $pages;
    }

    public function getPagesByType(string $type, SalesChannelContext $context): CmsPageCollection
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('cms_page.type', $type));

        $criteria->addAssociation('sections.backgroundMedia')
            ->addAssociation('sections.blocks.backgroundMedia')
            ->addAssociation('sections.blocks.slots');

        /** @var CmsPageCollection $pages */
        $pages = $this->cmsPageRepository->search($criteria, $context->getContext())->getEntities();

        foreach ($pages as $page) {
            $page->getSections()->sort(function (CmsSectionEntity $a, CmsSectionEntity $b) {
                return $a->getPosition() <=> $b->getPosition();
            });

            foreach ($page->getSections() as $section) {
                $section->getBlocks()->sort(function (CmsBlockEntity $a, CmsBlockEntity $b) {
                    return $a->getPosition() <=> $b->getPosition();
                });
            }
        }

        return $pages;
    }
}
