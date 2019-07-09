<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Dbal;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\FetchMode;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Read\EntityReaderInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Aggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\AvgAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\CountAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\EntityAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\MaxAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\MinAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\StatsAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\SumAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\ValueAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\ValueCountAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResultCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AvgResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\CountResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\EntityResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\MaxResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\MinResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\StatsResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\SumResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\ValueCountItem;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\ValueCountResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\ValueResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregatorResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntityAggregatorInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Parser\SqlQueryParser;
use Shopware\Core\Framework\Uuid\Uuid;

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
     * @var EntityReaderInterface
     */
    private $reader;

    /**
     * @var SqlQueryParser
     */
    private $queryParser;

    /**
     * @var EntityDefinitionQueryHelper
     */
    private $queryHelper;

    /**
     * @var DefinitionInstanceRegistry
     */
    private $registry;

    public function __construct(
        Connection $connection,
        EntityReaderInterface $reader,
        SqlQueryParser $queryParser,
        EntityDefinitionQueryHelper $queryHelper,
        DefinitionInstanceRegistry $registry
    ) {
        $this->connection = $connection;
        $this->reader = $reader;
        $this->queryParser = $queryParser;
        $this->queryHelper = $queryHelper;
        $this->registry = $registry;
    }

    public function aggregate(EntityDefinition $definition, Criteria $criteria, Context $context): AggregatorResult
    {
        $aggregations = new AggregationResultCollection();
        foreach ($criteria->getAggregations() as $aggregation) {
            $query = $this->createAggregationQuery($aggregation, $definition, $criteria, $context);

            $aggregationResult = $this->getAggregationResult($definition, $query, $aggregation, $context);
            $aggregations->add($aggregationResult);
        }

        return new AggregatorResult($aggregations, $context, $criteria);
    }

    private function createAggregationQuery(Aggregation $aggregation, EntityDefinition $definition, Criteria $criteria, Context $context): QueryBuilder
    {
        $criteria = clone $criteria;

        $table = $definition->getEntityName();

        $query = $this->queryHelper->getBaseQuery(new QueryBuilder($this->connection), $definition, $context);

        if ($definition->isInheritanceAware()) {
            $parent = $definition->getFields()->get('parent');
            $this->queryHelper->resolveField($parent, $definition, $definition->getEntityName(), $query, $context);
        }

        if ($aggregation->getFilter()) {
            $criteria->addFilter($aggregation->getFilter());
        }

        $fields = array_merge(
            $this->getFilterFields($criteria),
            $aggregation->getFields(),
            $aggregation->getGroupByFields()
        );

        //join association and translated fields
        foreach ($fields as $fieldName) {
            $this->queryHelper->resolveAccessor($fieldName, $definition, $table, $query, $context);
        }

        if ($definition->isInheritanceAware()) {
            $parent = $definition->getFields()->get('parent');
            $this->queryHelper->resolveField($parent, $definition, $table, $query, $context);
        }

        $filterQuery = new MultiFilter(MultiFilter::CONNECTION_AND, $criteria->getFilters());
        $parsed = $this->queryParser->parse($filterQuery, $definition, $context);
        if (!empty($parsed->getWheres())) {
            $query->andWhere(implode(' AND ', $parsed->getWheres()));
            foreach ($parsed->getParameters() as $key => $value) {
                $query->setParameter($key, $value, $parsed->getType($key));
            }
        }

        foreach ($aggregation->getGroupByFields() as $groupByField) {
            $accessor = $this->queryHelper->getFieldAccessor(
                $groupByField,
                $definition,
                $definition->getEntityName(),
                $context
            );

            $query->addSelect(sprintf('%s as `%s`', $accessor, $groupByField));
            $query->addGroupBy($accessor);
        }

        return $query;
    }

    private function getAggregationResult(
        EntityDefinition $definition,
        QueryBuilder $query,
        Aggregation $aggregation,
        Context $context
    ): AggregationResult {
        $accessor = $this->queryHelper->getFieldAccessor(
            $aggregation->getField(),
            $definition,
            $definition->getEntityName(),
            $context
        );

        $data = $this->fetchAggregation($definition, $query, $aggregation, $context, $accessor);

        return new AggregationResult($aggregation, $data);
    }

    private function fetchAggregation(EntityDefinition $definition, QueryBuilder $query, Aggregation $aggregation, Context $context, string $accessor): array
    {
        switch (true) {
            case $aggregation instanceof EntityAggregation:
                return $this->fetchEntityAggregation($definition, $query, $aggregation, $context, $accessor);
            case $aggregation instanceof ValueCountAggregation:
                return $this->fetchValueCountAggregation($definition, $query, $aggregation, $accessor);
            case $aggregation instanceof StatsAggregation:
                return $this->fetchStatsAggregation($definition, $query, $aggregation, $accessor);
            case $aggregation instanceof ValueAggregation:
                return $this->fetchValueAggregation($definition, $query, $aggregation, $accessor);
            case $aggregation instanceof AvgAggregation:
                return $this->fetchAvgAggregation($definition, $query, $aggregation, $accessor);
            case $aggregation instanceof MaxAggregation:
                return $this->fetchMaxAggregation($definition, $query, $aggregation, $accessor);
            case $aggregation instanceof CountAggregation:
                return $this->fetchCountAggregation($definition, $query, $aggregation, $accessor);
            case $aggregation instanceof MinAggregation:
                return $this->fetchMinAggregation($definition, $query, $aggregation, $accessor);
            case $aggregation instanceof SumAggregation:
                return $this->fetchSumAggregation($definition, $query, $aggregation, $accessor);
            default:
                throw new \RuntimeException(
                    sprintf('Aggregation of type %s not supported', \get_class($aggregation))
                );
        }
    }

    private function fetchEntityAggregation(EntityDefinition $definition, QueryBuilder $query, EntityAggregation $aggregation, Context $context, string $accessor): array
    {
        $query->addSelect([$accessor . 'as `id`']);
        $query->addGroupBy($accessor);

        $data = $query->execute()->fetchAll(FetchMode::ASSOCIATIVE);
        $data = array_map(function ($row) {
            if (!$row['id']) {
                return null;
            }

            $row['id'] = Uuid::fromBytesToHex($row['id']);

            return $row;
        }, $data);

        $data = array_filter($data);
        $ids = array_column($data, 'id');

        $relatedDefinition = $this->registry->get($aggregation->getDefinition());

        $entities = $this->reader->read($relatedDefinition, new Criteria($ids), $context);

        /** @var EntityResult[] $results */
        $results = [];
        foreach ($data as $row) {
            $key = $this->getAggregationKey($definition, $aggregation, $row);

            $hash = md5(json_encode($key));

            if (!isset($results[$hash])) {
                $results[$hash] = new EntityResult($key, $entities->getList([$row['id']]));
                continue;
            }

            $results[$hash]->add(
                $entities->get($row['id'])
            );
        }

        return array_values($results);
    }

    private function fetchValueCountAggregation(EntityDefinition $definition, QueryBuilder $query, ValueCountAggregation $aggregation, string $accessor): array
    {
        $query->addSelect([
            $accessor . ' as `key`',
            'COUNT(' . $accessor . ') as `count`',
        ]);
        $query->addGroupBy($accessor);

        $data = $query->execute()->fetchAll(FetchMode::ASSOCIATIVE);
        $field = $this->queryHelper->getField($aggregation->getField(), $definition, $definition->getEntityName());

        /** @var ValueCountResult[] $results */
        $results = [];
        foreach ($data as $row) {
            $key = $this->getAggregationKey($definition, $aggregation, $row);

            $value = $row['key'];
            try {
                $value = $field->getSerializer()->decode($field, $value);
            } catch (\Throwable $e) {
                $value = $this->tryToCast($value);
            }

            $value = new ValueCountItem($value, (int) $row['count']);

            $hash = md5(json_encode($key));
            if (!isset($results[$hash])) {
                $results[$hash] = new ValueCountResult($key, []);
            }

            $results[$hash]->add($value);
        }

        return array_values($results);
    }

    private function fetchStatsAggregation(EntityDefinition $definition, QueryBuilder $query, StatsAggregation $aggregation, string $accessor): array
    {
        $select = [];
        if ($aggregation->fetchCount()) {
            $select[] = 'COUNT(' . $accessor . ') as `count`';
        }
        if ($aggregation->fetchAvg()) {
            $select[] = 'AVG(' . $accessor . ') as `avg`';
        }
        if ($aggregation->fetchSum()) {
            $select[] = 'SUM(' . $accessor . ') as `sum`';
        }
        if ($aggregation->fetchMin()) {
            $select[] = 'MIN(' . $accessor . ') as `min`';
        }
        if ($aggregation->fetchMax()) {
            $select[] = 'MAX(' . $accessor . ') as `max`';
        }

        if (empty($select)) {
            throw new \RuntimeException('StatsAggregation configured without fetch');
        }

        $query->addSelect($select);

        $data = $query->execute()->fetchAll(FetchMode::ASSOCIATIVE);

        $results = [];
        foreach ($data as $row) {
            $key = $this->getAggregationKey($definition, $aggregation, $row);

            $results[] = new StatsResult(
                $key,
                isset($row['min']) ? (float) $row['min'] : null,
                isset($row['max']) ? (float) $row['max'] : null,
                isset($row['count']) ? (int) $row['count'] : null,
                isset($row['avg']) ? (float) $row['avg'] : null,
                isset($row['sum']) ? (float) $row['sum'] : null
            );
        }

        return $results;
    }

    private function fetchValueAggregation(EntityDefinition $definition, QueryBuilder $query, ValueAggregation $aggregation, string $accessor): array
    {
        $query->addSelect([$accessor . ' as `value`']);
        $query->addGroupBy($accessor);

        $data = $query->execute()->fetchAll(FetchMode::ASSOCIATIVE);
        $field = $this->queryHelper->getField($aggregation->getField(), $definition, $definition->getEntityName());

        /** @var ValueResult[] $results */
        $results = [];
        foreach ($data as $row) {
            $key = $this->getAggregationKey($definition, $aggregation, $row);

            $hash = md5(json_encode($key));

            $value = $row['value'];
            try {
                $value = $field->getSerializer()->decode($field, $value);
            } catch (\Throwable $e) {
                $value = $this->tryToCast($value);
            }

            if (!isset($results[$hash])) {
                $results[$hash] = new ValueResult($key, []);
            }

            $results[$hash]->add($value);
        }

        return array_values($results);
    }

    private function fetchAvgAggregation(EntityDefinition $definition, QueryBuilder $query, AvgAggregation $aggregation, string $accessor): array
    {
        $query->addSelect('AVG(' . $accessor . ') as `avg`');

        $data = $query->execute()->fetchAll(FetchMode::ASSOCIATIVE);

        $results = [];
        foreach ($data as $row) {
            $key = $this->getAggregationKey($definition, $aggregation, $row);

            $results[] = new AvgResult($key, (float) $row['avg']);
        }

        return $results;
    }

    private function fetchMaxAggregation(EntityDefinition $definition, QueryBuilder $query, MaxAggregation $aggregation, string $accessor): array
    {
        $query->addSelect('MAX(' . $accessor . ') as `max`');

        $data = $query->execute()->fetchAll(FetchMode::ASSOCIATIVE);

        $results = [];
        foreach ($data as $row) {
            $key = $this->getAggregationKey($definition, $aggregation, $row);

            $results[] = new MaxResult($key, $this->tryToCast($row['max']));
        }

        return $results;
    }

    private function fetchCountAggregation(EntityDefinition $definition, QueryBuilder $query, CountAggregation $aggregation, string $accessor): array
    {
        $query->addSelect('COUNT(' . $accessor . ') as `count`');

        $data = $query->execute()->fetchAll(FetchMode::ASSOCIATIVE);

        $results = [];
        foreach ($data as $row) {
            $key = $this->getAggregationKey($definition, $aggregation, $row);

            $results[] = new CountResult($key, (int) $row['count']);
        }

        return $results;
    }

    private function fetchMinAggregation(EntityDefinition $definition, QueryBuilder $query, MinAggregation $aggregation, string $accessor): array
    {
        $query->addSelect('MIN(' . $accessor . ') as `min`');

        $data = $query->execute()->fetchAll(FetchMode::ASSOCIATIVE);

        $results = [];
        foreach ($data as $row) {
            $key = $this->getAggregationKey($definition, $aggregation, $row);

            $results[] = new MinResult($key, $this->tryToCast($row['min']));
        }

        return $results;
    }

    private function fetchSumAggregation(EntityDefinition $definition, QueryBuilder $query, SumAggregation $aggregation, string $accessor): array
    {
        $query->addSelect('SUM(' . $accessor . ') as `sum`');

        $data = $query->execute()->fetchAll(FetchMode::ASSOCIATIVE);

        $results = [];
        foreach ($data as $row) {
            $key = $this->getAggregationKey($definition, $aggregation, $row);

            $results[] = new SumResult($key, (float) $row['sum']);
        }

        return $results;
    }

    /**
     * @return string[]
     */
    private function getFilterFields(Criteria $criteria): array
    {
        $fields = [];
        foreach ($criteria->getFilters() as $filter) {
            foreach ($filter->getFields() as $field) {
                $fields[] = $field;
            }
        }

        return $fields;
    }

    private function getAggregationKey(EntityDefinition $definition, Aggregation $aggregation, array $row): ?array
    {
        $key = null;

        foreach ($aggregation->getGroupByFields() as $groupByField) {
            $field = $this->queryHelper->getField($groupByField, $definition, $definition->getEntityName());

            $key[$groupByField] = $field->getSerializer()->decode($field, $row[$groupByField]);
        }

        return $key;
    }

    private function tryToCast($value)
    {
        if (is_numeric($value)) {
            // converts to either float or int
            $value += 0;
        } elseif (\is_string($value)) {
            try {
                $value = new \DateTime($value);
            } catch (\Throwable $e) {
                // no DateString -> just return it
            }
        }

        return $value;
    }
}
