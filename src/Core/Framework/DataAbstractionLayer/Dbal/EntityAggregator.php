<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Dbal;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\FetchMode;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Read\EntityReaderInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Aggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\AggregationResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\AggregationResultCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\AvgAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\CountAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\EntityAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\MaxAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\MinAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\StatsAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\SumAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\ValueAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\ValueCountAggregation;
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
        $table = $definition->getEntityName();

        $query = $this->queryHelper->getBaseQuery(new QueryBuilder($this->connection), $definition, $context);

        if ($definition->isInheritanceAware()) {
            $parent = $definition->getFields()->get('parent');
            $this->queryHelper->resolveField($parent, $definition, $definition->getEntityName(), $query, $context);
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
        $entities = $this->reader->read($this->registry->get($aggregation->getDefinition()), new Criteria($ids), $context);

        $data = $this->mapResult($definition, $aggregation, $data, function (array $current, array $row) use ($entities) {
            if (!\array_key_exists('entities', $current)) {
                $current['entities'] = $entities->getList([$row['id']]);
            } else {
                $current['entities']->add($entities->get($row['id']));
            }

            return $current;
        });

        return $data;
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
        $data = $this->mapResult($definition, $aggregation, $data, function (array $current, array $row) use ($field) {
            $value = $row['key'];
            try {
                $value = $field->getSerializer()->decode($field, $value);
            } catch (\Throwable $e) {
                $value = $this->tryToCast($value);
            }

            $current['values'][] = [
                'key' => $value,
                'count' => (int) $row['count'],
            ];

            return $current;
        });

        return $data;
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
        $data = $this->mapResult($definition, $aggregation, $data, function (array $current, array $row) {
            $result = [];

            if (\array_key_exists('count', $row)) {
                $result['count'] = (int) $row['count'];
            }
            if (\array_key_exists('avg', $row)) {
                $result['avg'] = (float) $row['avg'];
            }
            if (\array_key_exists('sum', $row)) {
                $result['sum'] = (float) $row['sum'];
            }
            if (\array_key_exists('min', $row)) {
                $result['min'] = $this->tryToCast($row['min']);
            }
            if (\array_key_exists('max', $row)) {
                $result['max'] = $this->tryToCast($row['max']);
            }

            return $result;
        });

        return $data;
    }

    private function fetchValueAggregation(EntityDefinition $definition, QueryBuilder $query, ValueAggregation $aggregation, string $accessor): array
    {
        $query->addSelect([$accessor . ' as `value`']);
        $query->addGroupBy($accessor);

        $data = $query->execute()->fetchAll(FetchMode::ASSOCIATIVE);
        $field = $this->queryHelper->getField($aggregation->getField(), $definition, $definition->getEntityName());
        $data = $this->mapResult($definition, $aggregation, $data, function (array $current, array $row) use ($field) {
            $value = $row['value'];
            try {
                $value = $field->getSerializer()->decode($field, $value);
            } catch (\Throwable $e) {
                $value = $this->tryToCast($value);
            }

            $current['values'][] = $value;

            return $current;
        });

        return $data;
    }

    private function fetchAvgAggregation(EntityDefinition $definition, QueryBuilder $query, AvgAggregation $aggregation, string $accessor): array
    {
        $query->addSelect('AVG(' . $accessor . ') as `avg`');

        $data = $query->execute()->fetchAll(FetchMode::ASSOCIATIVE);
        $data = $this->mapResult($definition, $aggregation, $data, function (array $current, array $row) {
            return ['avg' => (float) $row['avg']];
        });

        return $data;
    }

    private function fetchMaxAggregation(EntityDefinition $definition, QueryBuilder $query, MaxAggregation $aggregation, string $accessor): array
    {
        $query->addSelect('MAX(' . $accessor . ') as `max`');

        $data = $query->execute()->fetchAll(FetchMode::ASSOCIATIVE);
        $data = $this->mapResult($definition, $aggregation, $data, function (array $current, array $row) {
            return ['max' => $this->tryToCast($row['max'])];
        });

        return $data;
    }

    private function fetchCountAggregation(EntityDefinition $definition, QueryBuilder $query, CountAggregation $aggregation, string $accessor): array
    {
        $query->addSelect('COUNT(' . $accessor . ') as `count`');

        $data = $query->execute()->fetchAll(FetchMode::ASSOCIATIVE);
        $data = $this->mapResult($definition, $aggregation, $data, function (array $current, array $row) {
            return ['count' => (int) $row['count']];
        });

        return $data;
    }

    private function fetchMinAggregation(EntityDefinition $definition, QueryBuilder $query, MinAggregation $aggregation, string $accessor): array
    {
        $query->addSelect('MIN(' . $accessor . ') as `min`');

        $data = $query->execute()->fetchAll(FetchMode::ASSOCIATIVE);
        $data = $this->mapResult($definition, $aggregation, $data, function (array $current, array $row) {
            return ['min' => $this->tryToCast($row['min'])];
        });

        return $data;
    }

    private function fetchSumAggregation(EntityDefinition $definition, QueryBuilder $query, SumAggregation $aggregation, string $accessor): array
    {
        $query->addSelect('SUM(' . $accessor . ') as `sum`');

        $data = $query->execute()->fetchAll(FetchMode::ASSOCIATIVE);

        $data = $this->mapResult($definition, $aggregation, $data, function (array $current, array $row) {
            return ['sum' => (float) $row['sum']];
        });

        return $data;
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

    private function mapResult(EntityDefinition $definition, Aggregation $aggregation, array $data, callable $mapCallback): array
    {
        $data = array_reduce($data, function (array $carry, $row) use ($aggregation, $definition, $mapCallback) {
            $key = null;
            $id = '';
            foreach ($aggregation->getGroupByFields() as $groupByField) {
                $field = $this->queryHelper->getField($groupByField, $definition, $definition->getEntityName());
                $key[$groupByField] = $field->getSerializer()->decode($field, $row[$groupByField]);
                $id .= $row[$groupByField];
                unset($row[$groupByField]);
            }

            $current = \array_key_exists($id, $carry) ? $carry[$id] : [];
            $carry[$id] = $mapCallback($current, $row);
            $carry[$id]['key'] = $key;

            return $carry;
        }, []);

        return array_values($data);
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
