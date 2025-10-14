<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\Routing\IdResolution;

use Shopware\Core\Content\ContentSystem\ContentSystemException;
use Shopware\Core\Content\ContentSystem\Routing\Router\RouteMatchResult;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @internal
 */
#[Package('discovery')]
class EntityIdResolver
{
    public function __construct(
        private readonly ParameterExtractor $parameterExtractor,
        private readonly DefinitionInstanceRegistry $definitionRegistry,
    ) {
    }

    public function resolve(RouteMatchResult $match, SalesChannelContext $context): ResolvedData
    {
        $extracted = $this->parameterExtractor->extract($match);
        $resolutionParameters = $extracted->resolutionParameters;
        $passthroughParameters = $extracted->passthroughParameters;

        if ($resolutionParameters->isEmpty()) {
            return new ResolvedData(EntityIdMap::empty(), $passthroughParameters);
        }

        $grouped = $resolutionParameters->groupByEntityType();
        $resolvedEntityIdSets = [];
        foreach ($grouped as $entityType => $resolutionParameterGroup) {
            $ids = $this->resolveEntityIds($entityType, $resolutionParameterGroup, $context->getContext());

            $resolvedEntityIdSets[] = $ids;
        }

        return new ResolvedData(new EntityIdMap(array_merge([], ...$resolvedEntityIdSets)), $passthroughParameters);
    }

    /**
     * Batches all items into single query for performance.
     *
     * @param list<ResolutionParameter> $resolutionParameters
     *
     * @return array<string, string>
     */
    private function resolveEntityIds(string $entityType, array $resolutionParameters, Context $context): array
    {
        $criteria = $this->buildEntityCriteria($resolutionParameters);
        $result = $this->searchEntities($entityType, $criteria, $context);

        $resolvedIds = [];
        foreach ($resolutionParameters as $resolutionParameter) {
            $matchField = $resolutionParameter->resolutionConfig->matchField;
            $value = $resolutionParameter->value;
            $placeholder = $resolutionParameter->placeholder;

            $found = false;
            foreach ($result as $entity) {
                if ($entity->get($matchField) === $value) {
                    $resolvedIds[$placeholder] = $entity->getUniqueIdentifier();
                    $found = true;
                    break;
                }
            }

            if (!$found) {
                throw ContentSystemException::parameterResolutionFailed(
                    $entityType,
                    $matchField,
                    $value,
                    $placeholder
                );
            }
        }

        return $resolvedIds;
    }

    /**
     * @param list<ResolutionParameter> $resolutionParameters
     */
    private function buildEntityCriteria(array $resolutionParameters): Criteria
    {
        $filters = [];
        foreach ($resolutionParameters as $resolutionParameter) {
            $matchField = $resolutionParameter->resolutionConfig->matchField;
            $value = $resolutionParameter->value;
            $constraints = $resolutionParameter->resolutionConfig->constraints;

            $andFilters = [new EqualsFilter($matchField, $value)];

            foreach ($constraints as $field => $constraint) {
                $andFilters[] = $this->buildConstraintFilter($field, $constraint);
            }

            $filters[] = new MultiFilter(MultiFilter::CONNECTION_AND, $andFilters);
        }

        $criteria = new Criteria();
        $criteria->addFilter(new MultiFilter(MultiFilter::CONNECTION_OR, $filters));

        $matchFields = array_unique(array_map(
            fn ($item) => $item->resolutionConfig->matchField,
            $resolutionParameters
        ));

        foreach ($matchFields as $field) {
            if ($field !== 'id') {
                $criteria->addFields(['id', $field]);
            }
        }

        return $criteria;
    }

    private function buildConstraintFilter(string $field, mixed $constraint): MultiFilter|EqualsFilter
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

    /**
     * @return EntityCollection<covariant Entity>
     */
    private function searchEntities(string $entityType, Criteria $criteria, Context $context): EntityCollection
    {
        $repository = $this->definitionRegistry->getRepository($entityType);

        return $repository->search($criteria, $context)->getEntities();
    }
}
