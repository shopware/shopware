<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\Adapter\FactoryHelper;

use Shopware\Core\Content\ContentSystem\Adapter\Entity\ContentLayoutAssignmentInterface;
use Shopware\Core\Content\ContentSystem\Adapter\Entity\FooterContentLayout\FooterContentLayoutCollection;
use Shopware\Core\Content\ContentSystem\Adapter\Entity\HeaderContentLayout\HeaderContentLayoutCollection;
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
#[Package('discovery')]
class DomainAwareLayoutResolver
{
    /**
     * @template TCollection of HeaderContentLayoutCollection|FooterContentLayoutCollection
     *
     * @param EntityRepository<TCollection> $repository
     */
    public function resolve(
        SalesChannelContext $context,
        EntityRepository $repository
    ): ?ContentLayoutAssignmentInterface {
        $domainId = $context->getDomainId();
        $salesChannelId = $context->getSalesChannel()->getId();

        $criteria = new Criteria();

        if ($domainId !== null) {
            // When domain is known: match (domain + salesChannel) OR (no domain + salesChannel) OR (global)
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
        } else {
            // When domain is unknown: match (salesChannel only) OR (global)
            $criteria->addFilter(new EqualsFilter('domainId', null));
            $criteria->addFilter(new OrFilter([
                new EqualsFilter('salesChannelId', $salesChannelId),
                new EqualsFilter('salesChannelId', null),
            ]));
        }

        // Sort by specificity: non-null domainId first, then non-null salesChannelId
        $criteria->addSorting(new FieldSorting('domainId', FieldSorting::DESCENDING));
        $criteria->addSorting(new FieldSorting('salesChannelId', FieldSorting::DESCENDING));
        $criteria->setLimit(1);
        $criteria->addAssociation('contentLayout');

        return $repository->search($criteria, $context->getContext())->first();
    }
}
