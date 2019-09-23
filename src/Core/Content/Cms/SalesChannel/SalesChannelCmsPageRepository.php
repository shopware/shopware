<?php declare(strict_types=1);

namespace Shopware\Core\Content\Cms\SalesChannel;

use Shopware\Core\Content\Cms\CmsPageCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
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

        $criteria
            ->getAssociation('sections')
            ->addAssociation('backgroundMedia')
            ->addSorting(new FieldSorting('position', 'ASC'))
            ->getAssociation('blocks')
            ->addAssociation('backgroundMedia')
            ->addSorting(new FieldSorting('position', 'ASC'))
            ->addAssociation('slots');

        /** @var CmsPageCollection $pages */
        $pages = $this->cmsPageRepository->search($criteria, $context->getContext())->getEntities();

        return $pages;
    }

    public function getPagesByType(string $type, SalesChannelContext $context): CmsPageCollection
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('cms_page.type', $type));

        $criteria
            ->getAssociation('sections')
            ->addAssociation('backgroundMedia')
            ->addSorting(new FieldSorting('position', 'ASC'))
            ->getAssociation('blocks')
            ->addAssociation('backgroundMedia')
            ->addSorting(new FieldSorting('position', 'ASC'))
            ->addAssociation('slots');

        /** @var CmsPageCollection $pages */
        $pages = $this->cmsPageRepository->search($criteria, $context->getContext())->getEntities();

        return $pages;
    }
}
