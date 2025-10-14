<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\Routing\IdResolution;

use Shopware\Core\Content\ContentSystem\Routing\Router\RouteMatchResult;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @phpstan-import-type ResolutionParameterMap from ParameterExtractor
 * @phpstan-import-type ResolutionItem from ParameterExtractor
 *
 * @internal
 */
#[Package('discovery')]
class EntityIdResolver
{
    public function __construct(
        protected readonly DefinitionInstanceRegistry $definitionRegistry,
        protected readonly ParameterExtractor $parameterExtractor
    ) {
    }

    public function resolve(RouteMatchResult $match, SalesChannelContext $context): ?ResolvedData
    {
        $extracted = $this->parameterExtractor->extract($match);
        $resolutionParams = $extracted['resolution'];
        $passthroughParams = $extracted['passthrough'];

        if (empty($resolutionParams)) {
            return new ResolvedData(EntityIdMap::empty(), new ParameterMap($passthroughParams));
        }

        $grouped = $this->groupByEntityType($resolutionParams);
        $resolvedEntityIds = [];

        foreach ($grouped as $entityType => $items) {
            $ids = $this->resolveEntityType($entityType, $items, $context);

            if ($ids === null) {
                return null;
            }

            $resolvedEntityIds = \array_merge($resolvedEntityIds, $ids);
        }

        return new ResolvedData(new EntityIdMap($resolvedEntityIds), new ParameterMap($passthroughParams));
    }

    /**
     * @param ResolutionParameterMap $resolutionParams
     *
     * @return array<string, array<int, ResolutionItem>>
     */
    protected function groupByEntityType(array $resolutionParams): array
    {
        $grouped = [];

        foreach ($resolutionParams as $item) {
            $entityType = $item['resolution']['entity'] ?? null;

            if ($entityType === null) {
                continue;
            }

            $grouped[$entityType][] = $item;
        }

        return $grouped;
    }

    /**
     * Batches all items into single query for performance.
     *
     * @param array<int, ResolutionItem> $items
     *
     * @return array<string, string>|null
     */
    protected function resolveEntityType(string $entityType, array $items, SalesChannelContext $context): ?array
    {
        $definition = $this->getDefinition($entityType);
        $repository = $this->definitionRegistry->getRepository($definition->getEntityName());
        $criteria = new Criteria();

        $this->addVisibilityFilter($criteria, $entityType, $context);

        $itemFilters = [];

        foreach ($items as $item) {
            $matchField = $item['resolution']['match_field'] ?? 'id';
            $value = $item['value'];
            $constraints = $item['resolution']['constraints'] ?? [];

            $andFilters = [new EqualsFilter($matchField, $value)];

            foreach ($constraints as $field => $constraint) {
                $andFilters[] = $this->buildConstraintFilter($field, $constraint);
            }

            $itemFilters[] = new MultiFilter(MultiFilter::CONNECTION_AND, $andFilters);
        }

        $criteria->addFilter(new MultiFilter(MultiFilter::CONNECTION_OR, $itemFilters));

        $matchFields = array_unique(array_map(
            fn ($item) => $item['resolution']['match_field'] ?? 'id',
            $items
        ));

        foreach ($matchFields as $field) {
            if ($field !== 'id') {
                $criteria->addFields(['id', $field]);
            }
        }

        $result = $repository->search($criteria, $context->getContext());
        $resolvedIds = [];

        foreach ($items as $item) {
            $matchField = $item['resolution']['match_field'] ?? 'id';
            $value = $item['value'];
            $placeholder = $item['placeholder'];

            $found = false;
            foreach ($result as $entity) {
                $fieldValue = $matchField === 'id' ? $entity->getUniqueIdentifier() : $entity->get($matchField);

                if ($fieldValue === $value) {
                    $resolvedIds[$placeholder] = $entity->getUniqueIdentifier();
                    $found = true;
                    break;
                }
            }

            if (!$found) {
                return null;
            }
        }

        return $resolvedIds;
    }

    protected function getDefinition(string $entityType): EntityDefinition
    {
        return $this->definitionRegistry->getByEntityName($entityType);
    }

    protected function addVisibilityFilter(Criteria $criteria, string $entityType, SalesChannelContext $context): void
    {
        $salesChannelId = $context->getSalesChannel()->getId();

        match ($entityType) {
            'product' => $criteria->addFilter(
                new EqualsFilter('visibilities.salesChannelId', $salesChannelId)
            ),
            'category' => $criteria->addFilter(
                new EqualsFilter('active', true)
            ),
            default => null,
        };
    }

    /**
     * @param mixed $constraint
     */
    protected function buildConstraintFilter(string $field, $constraint): MultiFilter|EqualsFilter
    {
        if (\is_array($constraint)) {
            $filters = [];
            foreach ($constraint as $operator => $value) {
                $filters[] = new RangeFilter($field, [
                    $operator => $value,
                ]);
            }

            return new MultiFilter(MultiFilter::CONNECTION_AND, $filters);
        }

        return new EqualsFilter($field, $constraint);
    }
}
