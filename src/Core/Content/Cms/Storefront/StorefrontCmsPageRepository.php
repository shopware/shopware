<?php declare(strict_types=1);

namespace Shopware\Core\Content\Cms\Storefront;

use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Content\Cms\CmsPageCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;

class StorefrontCmsPageRepository
{
    /**
     * @var EntityRepositoryInterface
     */
    private $cmsPageRepository;

    public function __construct(EntityRepositoryInterface $repository)
    {
        $this->cmsPageRepository = $repository;
    }

    public function read(array $ids, CheckoutContext $context): CmsPageCollection
    {
        $criteria = new Criteria($ids);

        $blockCriteria = new Criteria();
        $blockCriteria->addAssociation('cms_block.slots');
        $blockCriteria->addSorting(new FieldSorting('position', 'ASC'));
        $criteria->addAssociation('cms_page.blocks', $blockCriteria);

        /** @var CmsPageCollection $pages */
        $pages = $this->cmsPageRepository->search($criteria, $context->getContext())->getEntities();

        return $pages;
    }
}
