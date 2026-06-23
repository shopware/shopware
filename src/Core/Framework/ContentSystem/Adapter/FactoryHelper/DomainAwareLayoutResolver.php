<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Adapter\FactoryHelper;

use Shopware\Core\Framework\ContentSystem\Adapter\Entity\AbstractContentLayoutAssignmentEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\OrFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * Resolves header/footer content layout assignments with domain-aware priority.
 *
 * Resolution priority (highest to lowest):
 * 1. Domain + SalesChannel (most specific)
 * 2. SalesChannel only
 * 3. Global (null domain, null salesChannel)
 *
 * @internal
 *
 * @final
 */
#[Package('framework')]
class DomainAwareLayoutResolver
{
    /**
     * @param EntityRepository<covariant EntityCollection<covariant AbstractContentLayoutAssignmentEntity>> $repository
     */
    public function resolve(
        SalesChannelContext $context,
        EntityRepository $repository
    ): ?AbstractContentLayoutAssignmentEntity {
        $domainId = $context->getDomainId();
        $salesChannelId = $context->getSalesChannel()->getId();

        $criteria = new Criteria();

        $criteria->addFilter(new OrFilter([
            new MultiFilter(MultiFilter::CONNECTION_AND, [
                new EqualsFilter('domainId', $domainId),
                new EqualsFilter('salesChannelId', $salesChannelId),
            ]),
            new MultiFilter(MultiFilter::CONNECTION_AND, [
                new EqualsFilter('domainId', null),
                new EqualsFilter('salesChannelId', $salesChannelId),
            ]),
            new MultiFilter(MultiFilter::CONNECTION_AND, [
                new EqualsFilter('domainId', null),
                new EqualsFilter('salesChannelId', null),
            ]),
        ]));

        // Sort by specificity: non-null domainId first, then non-null salesChannelId
        $criteria->addSorting(new FieldSorting('domainId', FieldSorting::DESCENDING));
        $criteria->addSorting(new FieldSorting('salesChannelId', FieldSorting::DESCENDING));
        $criteria->setLimit(1);
        $criteria->addAssociation('contentLayout');

        return $repository->search($criteria, $context->getContext())->first();
    }
}
