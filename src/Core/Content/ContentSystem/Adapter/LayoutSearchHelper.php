<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\Adapter;

use Shopware\Core\Content\Category\Aggregate\CategoryContentLayout\CategoryContentLayoutCollection;
use Shopware\Core\Content\Product\Aggregate\ProductContentLayout\ProductContentLayoutCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\OrFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @internal
 */
#[Package('discovery')]
class LayoutSearchHelper
{
    /**
     * Resolves layout ID with sales channel fallback priority: specific → global (null)
     *
     * @template TEntityCollection of CategoryContentLayoutCollection|ProductContentLayoutCollection
     *
     * @param EntityRepository<TEntityCollection> $repository
     */
    public static function buildLayoutIdSearchCriteria(
        string $entityIdField,
        string $entityId,
        SalesChannelContext $salesChannelContext,
        EntityRepository $repository
    ): ?string {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter($entityIdField, $entityId));
        $criteria->addFilter(new OrFilter([
            new EqualsFilter('salesChannelId', $salesChannelContext->getSalesChannel()->getId()),
            new EqualsFilter('salesChannelId', null),
        ]));
        $criteria->addSorting(new FieldSorting('salesChannelId', FieldSorting::DESCENDING));
        $criteria->setLimit(1);

        $result = $repository->search($criteria, $salesChannelContext->getContext());
        $entity = $result->first();

        return $entity?->getContentLayoutId();
    }
}
