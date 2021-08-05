<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Dbal;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InvalidAggregationQueryException;
use Shopware\Core\Framework\DataAbstractionLayer\Field\AssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Aggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Bucket\BucketAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Bucket\DateHistogramAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Bucket\FilterAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Bucket\TermsAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Metric\AvgAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Metric\CountAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Metric\EntityAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Metric\MaxAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Metric\MinAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Metric\StatsAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Metric\SumAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResultCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Bucket\Bucket;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Bucket\DateHistogramResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Bucket\TermsResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Metric\AvgResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Metric\CountResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Metric\EntityResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Metric\MaxResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Metric\MinResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Metric\StatsResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Metric\SumResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntityAggregatorInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;

/**
 * Allows to execute aggregated queries for all entities in the system
 */
class EntityAggregator implements EntityAggregatorInterface
{
    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var EntityDefinitionQueryHelper
     */
    private $helper;

    /**
     * @var DefinitionInstanceRegistry
     */
    private $registry;

    /**
     * @var CriteriaQueryBuilder
     */
    private $criteriaQueryBuilder;

    public function __construct(
        Connection $connection,
        EntityDefinitionQueryHelper $queryHelper,
        DefinitionInstanceRegistry $registry,
        CriteriaQueryBuilder $criteriaQueryBuilder
    ) {
        $this->connection = $connection;
        $this->helper = $queryHelper;
        $this->registry = $registry;
        $this->criteriaQueryBuilder = $criteriaQueryBuilder;
    }

    public function aggregate(EntityDefinition $definition, Criteria $criteria, Context $context): AggregationResultCollection
    {
        $aggregations = new AggregationResultCollection();
        foreach ($criteria->getAggregations() as $aggregation) {
            $result = $this->fetchAggregation($aggregation, $definition, $criteria, $context);
            $aggregations->add($result);
        }

        return $aggregations;
    }

    public static function formatDate(string $interval, \DateTime $date): string
    {
        switch ($interval) {
            case DateHistogramAggregation::PER_MINUTE:
                return $date->format('Y-m-d H:i:00');
            case DateHistogramAggregation::PER_HOUR:
                return $date->format('Y-m-d H:00:00');
            case DateHistogramAggregation::PER_DAY:
                return $date->format('Y-m-d 00:00:00');
            case DateHistogramAggregation::PER_WEEK:
                return $date->format('Y W');
            case DateHistogramAggregation::PER_MONTH:
                return $date->format('Y-m-01 00:00:00');
            case DateHistogramAggregation::PER_QUARTER:
                $month = (int) $date->format('m');

                return $date->format('Y') . ' ' . ceil($month / 3);
            case DateHistogramAggregation::PER_YEAR:
                return $date->format('Y-01-01 00:00:00');
            default:
                throw new \RuntimeException('Provided date format is not supported');
        }
    }

    private function fetchAggregation(Aggregation $aggregation, EntityDefinition $definition, Criteria $criteria, Context $context): AggregationResult
    {
        $clone = clone $criteria;
        $clone->resetAggregations();
        $clone->resetSorting();
        $clone->resetPostFilters();
        $clone->resetGroupFields();

        $query = new QueryBuilder($this->connection);

        // If an aggregation is to be created on a to many association that is already stored as a filter.
        // The association is therefore referenced twice in the query and would have to be created as a sub-join in each case. But since only the filters are considered, the association is referenced only once.
        // In this case we add the aggregation field as path to the criteria builder and the join group builder will consider this path for the sub-join logic
        $paths = [$this->findToManyPath($aggregation, $definition)];

        $query = $this->criteriaQueryBuilder->build($query, $definition, $clone, $context, array_filter($paths));
        $query->resetQueryPart('orderBy');

        if ($criteria->getTitle()) {
            $query->setTitle($criteria->getTitle() . '::aggregation::' . $aggregation->getName());
        }

        $this->helper->addIdCondition($criteria, $definition, $query);

        $table = $definition->getEntityName();

        foreach ($aggregation->getFields() as $fieldName) {
            $this->helper->resolveAccessor($fieldName, $definition, $table, $query, $context, $aggregation);
        }

        $query->resetQueryPart('groupBy');

        $this->extendQuery($aggregation, $query, $definition, $context);

        $rows = $query->execute()->fetchAll(\PDO::FETCH_ASSOC);

        return $this->hydrateResult($aggregation, $definition, $rows, $context);
    }

    private function findToManyPath(Aggregation $aggregation, EntityDefinition $definition): ?string
    {
        $fields = EntityDefinitionQueryHelper::getFieldsOfAccessor($definition, $aggregation->getField(), false);

        $fields = array_filter($fields);

        if (\count($fields) === 0) {
            return null;
        }

        // contains later the path to the first to many association
        $path = [$definition->getEntityName()];

        $found = false;

        /** @var Field $field */
        foreach ($fields as $field) {
            if (!($field instanceof AssociationField)) {
                break;
            }

            // if to many not already detected, continue with path building
            $path[] = $field->getPropertyName();

            if ($field instanceof ManyToManyAssociationField || $field instanceof OneToManyAssociationField) {
                $found = true;
            }
        }

        if ($found) {
            return implode('.', $path);
        }

        return null;
    }

    private function extendQuery(Aggregation $aggregation, QueryBuilder $query, EntityDefinition $definition, Context $context): void
    {
        switch (true) {
            case $aggregation instanceof DateHistogramAggregation:

                $this->parseDateHistogramAggregation($aggregation, $query, $definition, $context);

                break;

            case $aggregation instanceof TermsAggregation:

                $this->parseTermsAggregation($aggregation, $query, $definition, $context);

                break;

            case $aggregation instanceof FilterAggregation:

                $this->parseFilterAggregation($aggregation, $query, $definition, $context);

                break;

            case $aggregation instanceof AvgAggregation:

                $this->parseAvgAggregation($aggregation, $query, $definition, $context);

                break;

            case $aggregation instanceof SumAggregation:

                $this->parseSumAggregation($aggregation, $query, $definition, $context);

                break;

            case $aggregation instanceof MaxAggregation:

                $this->parseMaxAggregation($aggregation, $query, $definition, $context);

                break;

            case $aggregation instanceof MinAggregation:

                $this->parseMinAggregation($aggregation, $query, $definition, $context);

                break;

            case $aggregation instanceof CountAggregation:

                $this->parseCountAggregation($aggregation, $query, $definition, $context);

                break;

            case $aggregation instanceof StatsAggregation:

                $this->parseStatsAggregation($aggregation, $query, $definition, $context);

                break;

            case $aggregation instanceof EntityAggregation:

                $this->parseEntityAggregation($aggregation, $query, $definition, $context);

                break;

            default:
                throw new InvalidAggregationQueryException(sprintf('Aggregation of type %s not supported', \get_class($aggregation)));
        }
    }

    private function parseFilterAggregation(FilterAggregation $aggregation, QueryBuilder $query, EntityDefinition $definition, Context $context): void
    {
        if (!empty($aggregation->getFilter())) {
            $this->criteriaQueryBuilder->addFilter($definition, new MultiFilter(MultiFilter::CONNECTION_AND, $aggregation->getFilter()), $query, $context);
        }

        $this->extendQuery($aggregation->getAggregation(), $query, $definition, $context);
    }

    private function parseDateHistogramAggregation(DateHistogramAggregation $aggregation, QueryBuilder $query, EntityDefinition $definition, Context $context): void
    {
        $accessor = $this->helper->getFieldAccessor($aggregation->getField(), $definition, $definition->getEntityName(), $context);

        if ($aggregation->getTimeZone()) {
            $accessor = 'CONVERT_TZ(' . $accessor . ', "UTC", "' . $aggregation->getTimeZone() . '")';
        }

        switch ($aggregation->getInterval()) {
            case DateHistogramAggregation::PER_MINUTE:
                $groupBy = 'DATE_FORMAT(' . $accessor . ', \'%Y-%m-%d %H:%i\')';

                break;
            case DateHistogramAggregation::PER_HOUR:
                $groupBy = 'DATE_FORMAT(' . $accessor . ', \'%Y-%m-%d %H\')';

                break;
            case DateHistogramAggregation::PER_DAY:
                $groupBy = 'DATE_FORMAT(' . $accessor . ', \'%Y-%m-%d\')';

                break;
            case DateHistogramAggregation::PER_WEEK:
                $groupBy = 'DATE_FORMAT(' . $accessor . ', \'%Y-%v\')';

                break;
            case DateHistogramAggregation::PER_MONTH:
                $groupBy = 'DATE_FORMAT(' . $accessor . ', \'%Y-%m\')';

                break;
            case DateHistogramAggregation::PER_QUARTER:
                $groupBy = 'CONCAT(DATE_FORMAT(' . $accessor . ', \'%Y\'), \'-\', QUARTER(' . $accessor . '))';

                break;
            case DateHistogramAggregation::PER_YEAR:
                $groupBy = 'DATE_FORMAT(' . $accessor . ', \'%Y\')';

                break;

            default:
                throw new \RuntimeException('Provided date format is not supported');
        }
        $query->addGroupBy($groupBy);

        $key = $aggregation->getName() . '.key';
        $query->addSelect(sprintf('MIN(%s) as `%s`', $accessor, $key));

        $key = $aggregation->getName() . '.count';
        $countAccessor = $this->helper->getFieldAccessor('id', $definition, $definition->getEntityName(), $context);
        $query->addSelect(sprintf('COUNT(%s) as `%s`', $countAccessor, $key));

        if ($aggregation->getSorting()) {
            $this->addSorting($aggregation->getSorting(), $definition, $query, $context);
        } else {
            $query->addOrderBy($accessor);
        }

        if ($aggregation->getAggregation()) {
            $this->extendQuery($aggregation->getAggregation(), $query, $definition, $context);
        }
    }

    private function parseTermsAggregation(TermsAggregation $aggregation, QueryBuilder $query, EntityDefinition $definition, Context $context): void
    {
        $keyAccessor = $this->helper->getFieldAccessor($aggregation->getField(), $definition, $definition->getEntityName(), $context);
        $query->addGroupBy($keyAccessor);

        $key = $aggregation->getName() . '.key';

        $field = $this->helper->getField($aggregation->getField(), $definition, $definition->getEntityName());
        if ($field instanceof FkField || $field instanceof IdField) {
            $keyAccessor = 'LOWER(HEX(' . $keyAccessor . '))';
        }

        $query->addSelect(sprintf('%s as `%s`', $keyAccessor, $key));

        $key = $aggregation->getName() . '.count';

        $countAccessor = $this->helper->getFieldAccessor('id', $definition, $definition->getEntityName(), $context);
        $query->addSelect(sprintf('COUNT(%s) as `%s`', $countAccessor, $key));

        if ($aggregation->getLimit()) {
            $query->setMaxResults($aggregation->getLimit());
        }

        if ($aggregation->getSorting()) {
            $this->addSorting($aggregation->getSorting(), $definition, $query, $context);
        }

        if ($aggregation->getAggregation()) {
            $this->extendQuery($aggregation->getAggregation(), $query, $definition, $context);
        }
    }

    private function parseAvgAggregation(AvgAggregation $aggregation, QueryBuilder $query, EntityDefinition $definition, Context $context): void
    {
        $accessor = $this->helper->getFieldAccessor($aggregation->getField(), $definition, $definition->getEntityName(), $context);

        $query->addSelect(sprintf('AVG(%s) as `%s`', $accessor, $aggregation->getName()));
    }

    private function parseSumAggregation(SumAggregation $aggregation, QueryBuilder $query, EntityDefinition $definition, Context $context): void
    {
        $accessor = $this->helper->getFieldAccessor($aggregation->getField(), $definition, $definition->getEntityName(), $context);

        $query->addSelect(sprintf('SUM(%s) as `%s`', $accessor, $aggregation->getName()));
    }

    private function parseMaxAggregation(MaxAggregation $aggregation, QueryBuilder $query, EntityDefinition $definition, Context $context): void
    {
        $accessor = $this->helper->getFieldAccessor($aggregation->getField(), $definition, $definition->getEntityName(), $context);

        $query->addSelect(sprintf('MAX(%s) as `%s`', $accessor, $aggregation->getName()));
    }

    private function parseMinAggregation(MinAggregation $aggregation, QueryBuilder $query, EntityDefinition $definition, Context $context): void
    {
        $accessor = $this->helper->getFieldAccessor($aggregation->getField(), $definition, $definition->getEntityName(), $context);

        $query->addSelect(sprintf('MIN(%s) as `%s`', $accessor, $aggregation->getName()));
    }

    private function parseCountAggregation(CountAggregation $aggregation, QueryBuilder $query, EntityDefinition $definition, Context $context): void
    {
        $accessor = $this->helper->getFieldAccessor($aggregation->getField(), $definition, $definition->getEntityName(), $context);

        $query->addSelect(sprintf('COUNT(DISTINCT %s) as `%s`', $accessor, $aggregation->getName()));
    }

    private function parseStatsAggregation(StatsAggregation $aggregation, QueryBuilder $query, EntityDefinition $definition, Context $context): void
    {
        $accessor = $this->helper->getFieldAccessor($aggregation->getField(), $definition, $definition->getEntityName(), $context);

        if ($aggregation->fetchAvg()) {
            $query->addSelect(sprintf('AVG(%s) as `%s.avg`', $accessor, $aggregation->getName()));
        }
        if ($aggregation->fetchMin()) {
            $query->addSelect(sprintf('MIN(%s) as `%s.min`', $accessor, $aggregation->getName()));
        }
        if ($aggregation->fetchMax()) {
            $query->addSelect(sprintf('MAX(%s) as `%s.max`', $accessor, $aggregation->getName()));
        }
        if ($aggregation->fetchSum()) {
            $query->addSelect(sprintf('SUM(%s) as `%s.sum`', $accessor, $aggregation->getName()));
        }
    }

    private function parseEntityAggregation(EntityAggregation $aggregation, QueryBuilder $query, EntityDefinition $definition, Context $context): void
    {
        $accessor = $this->helper->getFieldAccessor($aggregation->getField(), $definition, $definition->getEntityName(), $context);
        $query->addGroupBy($accessor);

        $accessor = 'LOWER(HEX(' . $accessor . '))';
        $query->addSelect(sprintf('%s as `%s`', $accessor, $aggregation->getName()));
    }

    private function hydrateResult(Aggregation $aggregation, EntityDefinition $definition, array $rows, Context $context): AggregationResult
    {
        $name = $aggregation->getName();

        switch (true) {
            case $aggregation instanceof DateHistogramAggregation:

                return $this->hydrateDateHistogramAggregation($aggregation, $definition, $rows, $context);

            case $aggregation instanceof TermsAggregation:

                return $this->hydrateTermsAggregation($aggregation, $definition, $rows, $context);

            case $aggregation instanceof FilterAggregation:

                return $this->hydrateResult($aggregation->getAggregation(), $definition, $rows, $context);

            case $aggregation instanceof AvgAggregation:
                $value = isset($rows[0]) ? $rows[0][$name] : 0;

                return new AvgResult($aggregation->getName(), (float) $value);

            case $aggregation instanceof SumAggregation:
                $value = isset($rows[0]) ? $rows[0][$name] : 0;

                return new SumResult($aggregation->getName(), (float) $value);

            case $aggregation instanceof MaxAggregation:
                $value = isset($rows[0]) ? $rows[0][$name] : 0;

                return new MaxResult($aggregation->getName(), $value);

            case $aggregation instanceof MinAggregation:
                $value = isset($rows[0]) ? $rows[0][$name] : 0;

                return new MinResult($aggregation->getName(), $value);

            case $aggregation instanceof CountAggregation:
                $value = isset($rows[0]) ? $rows[0][$name] : 0;

                return new CountResult($aggregation->getName(), (int) $value);

            case $aggregation instanceof StatsAggregation:
                if (empty($rows)) {
                    return new StatsResult($aggregation->getName(), 0, 0, 0.0, 0.0);
                }

                $min = $rows[0][$name . '.min'] ?? null;
                $max = $rows[0][$name . '.max'] ?? null;
                $avg = isset($rows[0][$name . '.avg']) ? (float) $rows[0][$name . '.avg'] : null;
                $sum = isset($rows[0][$name . '.sum']) ? (float) $rows[0][$name . '.sum'] : null;

                return new StatsResult($aggregation->getName(), $min, $max, $avg, $sum);

            case $aggregation instanceof EntityAggregation:

                return $this->hydrateEntityAggregation($aggregation, $rows, $context);
            default:
                throw new InvalidAggregationQueryException(sprintf('Aggregation of type %s not supported', \get_class($aggregation)));
        }
    }

    private function hydrateEntityAggregation(EntityAggregation $aggregation, array $rows, Context $context): EntityResult
    {
        $ids = array_filter(array_column($rows, $aggregation->getName()));

        if (empty($ids)) {
            return new EntityResult($aggregation->getName(), new EntityCollection());
        }

        $repository = $this->registry->getRepository($aggregation->getEntity());

        $entities = $repository->search(new Criteria($ids), $context);

        return new EntityResult($aggregation->getName(), $entities->getEntities());
    }

    private function hydrateDateHistogramAggregation(DateHistogramAggregation $aggregation, EntityDefinition $definition, array $rows, Context $context): DateHistogramResult
    {
        if (empty($rows)) {
            return new DateHistogramResult($aggregation->getName(), []);
        }

        $buckets = [];

        $grouped = $this->groupBuckets($aggregation, $rows);

        foreach ($grouped as $value => $group) {
            $count = $group['count'];
            $nested = null;

            if ($aggregation->getAggregation()) {
                $nested = $this->hydrateResult($aggregation->getAggregation(), $definition, $group['buckets'], $context);
            }

            $date = new \DateTime($value);

            if ($aggregation->getFormat()) {
                $value = $date->format($aggregation->getFormat());
            } else {
                $value = self::formatDate($aggregation->getInterval(), $date);
            }

            $buckets[] = new Bucket($value, $count, $nested);
        }

        return new DateHistogramResult($aggregation->getName(), $buckets);
    }

    private function hydrateTermsAggregation(TermsAggregation $aggregation, EntityDefinition $definition, array $rows, Context $context): TermsResult
    {
        $buckets = [];

        $grouped = $this->groupBuckets($aggregation, $rows);

        foreach ($grouped as $value => $group) {
            $count = $group['count'];
            $nested = null;

            if ($aggregation->getAggregation()) {
                $nested = $this->hydrateResult($aggregation->getAggregation(), $definition, $group['buckets'], $context);
            }

            $buckets[] = new Bucket((string) $value, $count, $nested);
        }

        return new TermsResult($aggregation->getName(), array_values($buckets));
    }

    private function addSorting(FieldSorting $sorting, EntityDefinition $definition, QueryBuilder $query, Context $context): void
    {
        if ($sorting->getField() !== '_count') {
            $this->criteriaQueryBuilder->addSortings($definition, new Criteria(), [$sorting], $query, $context);

            return;
        }

        $countAccessor = $this->helper->getFieldAccessor('id', $definition, $definition->getEntityName(), $context);
        $countAccessor = sprintf('COUNT(%s)', $countAccessor);

        $direction = $sorting->getDirection() === FieldSorting::ASCENDING ? FieldSorting::ASCENDING : FieldSorting::DESCENDING;

        $query->addOrderBy($countAccessor, $direction);
    }

    private function groupBuckets(BucketAggregation $aggregation, array $rows): array
    {
        $valueKey = $aggregation->getName() . '.key';

        $countKey = $aggregation->getName() . '.count';

        $grouped = [];
        foreach ($rows as $row) {
            $value = $row[$valueKey];
            $count = (int) $row[$countKey];

            if (isset($grouped[$value])) {
                $grouped[$value]['count'] += $count;
            } else {
                $grouped[$value] = ['count' => $count, 'buckets' => []];
            }

            if ($aggregation->getAggregation()) {
                $grouped[$value]['buckets'][] = $row;
            }
        }

        return $grouped;
    }
}
