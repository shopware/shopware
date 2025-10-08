<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\Routing\LayoutResolution;

use Shopware\Core\Content\ContentSystem\Layout\Entity\ContentLayoutAssignmentCollection;
use Shopware\Core\Content\ContentSystem\Layout\Entity\ContentLayoutAssignmentEntity;
use Shopware\Core\Content\ContentSystem\Routing\IdResolution\Struct\ResolvedData;
use Shopware\Core\Content\ContentSystem\Routing\LayoutResolution\Cascade\CascadeStepFactory;
use Shopware\Core\Content\ContentSystem\Routing\LayoutResolution\Cascade\LayoutCascade;
use Shopware\Core\Content\ContentSystem\Routing\Struct\RouteMatchResult;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\OrFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * Resolves layouts via cascade (first match wins, array order = priority).
 *
 * @internal
 */
#[Package('discovery')]
final class LayoutResolver
{
    /**
     * @param EntityRepository<ContentLayoutAssignmentCollection> $assignmentRepository
     */
    public function __construct(
        private readonly EntityRepository $assignmentRepository,
        private readonly CascadeStepFactory $cascadeStepFactory
    ) {
    }

    public function resolve(RouteMatchResult $match, ResolvedData $resolvedData, SalesChannelContext $context): ?string
    {
        $route = $match->getRoute();
        $cascade = LayoutCascade::fromArray($route->getLayoutCascade(), $this->cascadeStepFactory);

        if ($cascade === null) {
            return null;
        }

        $assignments = $this->loadAllAssignments($cascade, $resolvedData, $context);

        return $cascade->resolve($assignments, $resolvedData, $context);
    }

    /**
     * @return EntityCollection<ContentLayoutAssignmentEntity>
     */
    private function loadAllAssignments(
        LayoutCascade $cascade,
        ResolvedData $resolvedData,
        SalesChannelContext $context
    ): EntityCollection {
        $criteria = new Criteria();

        $criteria->addFields([
            'id',
            'entityType',
            'entityId',
            'layoutId',
            'salesChannelId',
        ]);

        $filters = $cascade->buildFilters($resolvedData, $context);

        if (empty($filters)) {
            return new EntityCollection();
        }

        $criteria->addFilter(new OrFilter($filters));
        $criteria->addFilter(
            new EqualsFilter('salesChannelId', $context->getSalesChannel()->getId())
        );

        $result = $this->assignmentRepository->search($criteria, $context->getContext())->getEntities();

        return $result;
    }
}
