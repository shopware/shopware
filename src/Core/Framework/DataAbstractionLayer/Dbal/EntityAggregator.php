<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Dbal;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\FetchMode;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Read\EntityReaderInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Read\ReadCriteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Aggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\AggregationResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\AggregationResultCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\AvgAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\AvgAggregationResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\CardinalityAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\CardinalityAggregationResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\CountAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\CountAggregationResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\EntityAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\EntityAggregationResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\MaxAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\MaxAggregationResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\MinAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\MinAggregationResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\StatsAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\StatsAggregationResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\SumAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\SumAggregationResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\ValueCountAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\ValueCountAggregationResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregatorResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntityAggregatorInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Parser\SqlQueryParser;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Query\NestedQuery;
use Shopware\Core\Framework\Struct\Uuid;

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

    public function __construct(
        Connection $connection,
        EntityReaderInterface $reader,
        SqlQueryParser $queryParser,
        EntityDefinitionQueryHelper $queryHelper
    ) {
        $this->connection = $connection;
        $this->reader = $reader;
        $this->queryParser = $queryParser;
        $this->queryHelper = $queryHelper;
    }

    public function aggregate(string $definition, Criteria $criteria, Context $context): AggregatorResult
    {
        $aggregations = new AggregationResultCollection();
        foreach ($criteria->getAggregations() as $aggregation) {
            $query = $this->createAggregationQuery($aggregation, $definition, $criteria, $context);

            $aggregationResult = $this->fetchAggregation($definition, $query, $aggregation, $context);
            $aggregations->add($aggregationResult);
        }

        return new AggregatorResult($aggregations, $context, $criteria);
    }

    private function createAggregationQuery(Aggregation $aggregation, string $definition, Criteria $criteria, Context $context): QueryBuilder
    {
        /** @var EntityDefinition $definition */
        $table = $definition::getEntityName();

        $query = $this->queryHelper->getBaseQuery(new QueryBuilder($this->connection), $definition, $context);

        if ($definition::isInheritanceAware()) {
            /** @var EntityDefinition|string $definition */
            $parent = $definition::getFields()->get('parent');
            $this->queryHelper->resolveField($parent, $definition, $definition::getEntityName(), $query, $context);
        }

        $fields = array_merge(
            $this->getFilterFields($criteria),
            $aggregation->getFields()
        );

        //join association and translated fields
        foreach ($fields as $fieldName) {
            $this->queryHelper->resolveAccessor($fieldName, $definition, $table, $query, $context);
        }

        if ($definition::isInheritanceAware()) {
            /** @var EntityDefinition|string $definition */
            $parent = $definition::getFields()->get('parent');
            $this->queryHelper->resolveField($parent, $definition, $table, $query, $context);
        }

        $filterQuery = new NestedQuery($criteria->getFilters());
        $parsed = $this->queryParser->parse($filterQuery, $definition, $context);
        if (!empty($parsed->getWheres())) {
            $query->andWhere(implode(' AND ', $parsed->getWheres()));
            foreach ($parsed->getParameters() as $key => $value) {
                $query->setParameter($key, $value, $parsed->getType($key));
            }
        }

        return $query;
    }

    private function fetchAggregation(string $definition, QueryBuilder $query, Aggregation $aggregation, Context $context): AggregationResult
    {
        /** @var EntityDefinition|string $definition */
        $accessor = $this->queryHelper->getFieldAccessor(
            $aggregation->getField(),
            $definition,
            $definition::getEntityName(),
            $context
        );

        if ($aggregation instanceof EntityAggregation) {
            $query->select([$accessor]);
            $query->groupBy($accessor);

            $ids = $query->execute()->fetchAll(FetchMode::COLUMN);
            $ids = array_filter($ids);

            $ids = array_map(function ($bytes) {
                return Uuid::fromBytesToHex($bytes);
            }, $ids);

            $data = $this->reader->read($aggregation->getDefinition(), new ReadCriteria($ids), $context);

            return new EntityAggregationResult($aggregation, $data);
        }

        if ($aggregation instanceof ValueCountAggregation) {
            $query->select([
                $accessor . ' as `key`',
                'COUNT(' . $accessor . ')' . ' as `count`',
            ]);
            $query->groupBy($accessor);

            $data = $query->execute()->fetchAll(FetchMode::ASSOCIATIVE);

            return new ValueCountAggregationResult($aggregation, $data);
        }

        if ($aggregation instanceof StatsAggregation) {
            $select = [];
            if ($aggregation->fetchCount()) {
                $select[] = 'COUNT(' . $accessor . ')' . ' as `count`';
            }
            if ($aggregation->fetchAvg()) {
                $select[] = 'AVG(' . $accessor . ')' . ' as `avg`';
            }
            if ($aggregation->fetchSum()) {
                $select[] = 'SUM(' . $accessor . ')' . ' as `sum`';
            }
            if ($aggregation->fetchMin()) {
                $select[] = 'MIN(' . $accessor . ')' . ' as `min`';
            }
            if ($aggregation->fetchMax()) {
                $select[] = 'MAX(' . $accessor . ')' . ' as `max`';
            }

            if (empty($select)) {
                throw new \RuntimeException('StatsAggregation configured without fetch');
            }

            $query->select($select);

            $data = $query->execute()->fetch(FetchMode::ASSOCIATIVE);

            return new StatsAggregationResult(
                $aggregation,
                $aggregation->fetchCount() ? (int) $data['count'] : null,
                $aggregation->fetchAvg() ? (float) $data['avg'] : null,
                $aggregation->fetchSum() ? (float) $data['sum'] : null,
                $aggregation->fetchMin() ? (float) $data['min'] : null,
                $aggregation->fetchMax() ? (float) $data['max'] : null
            );
        }

        if ($aggregation instanceof CardinalityAggregation) {
            $query->select([$accessor]);
            $query->groupBy($accessor);

            $data = $query->execute()->fetchAll(FetchMode::COLUMN);

            return new CardinalityAggregationResult($aggregation, \count($data));
        }

        if ($aggregation instanceof AvgAggregation) {
            $query->select('AVG(' . $accessor . ') as `avg`');

            $data = $query->execute()->fetch(FetchMode::ASSOCIATIVE);

            return new AvgAggregationResult($aggregation, (float) $data['avg']);
        }

        if ($aggregation instanceof MaxAggregation) {
            $query->select('MAX(' . $accessor . ') as `max`');

            $data = $query->execute()->fetch(FetchMode::ASSOCIATIVE);

            return new MaxAggregationResult($aggregation, (float) $data['max']);
        }

        if ($aggregation instanceof CountAggregation) {
            $query->select('COUNT(' . $accessor . ') as `count`');

            $data = $query->execute()->fetch(FetchMode::ASSOCIATIVE);

            return new CountAggregationResult($aggregation, (int) $data['count']);
        }

        if ($aggregation instanceof MinAggregation) {
            $query->select('MIN(' . $accessor . ') as `min`');

            $data = $query->execute()->fetch(FetchMode::ASSOCIATIVE);

            return new MinAggregationResult($aggregation, (float) $data['min']);
        }

        if ($aggregation instanceof SumAggregation) {
            $query->select('SUM(' . $accessor . ') as `sum`');

            $data = $query->execute()->fetch(FetchMode::ASSOCIATIVE);

            return new SumAggregationResult($aggregation, (float) $data['sum']);
        }

        throw new \RuntimeException(
            sprintf('Aggregation of type %s not supported', \get_class($aggregation))
        );
    }

    /**
     * @param Criteria $criteria
     *
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
}
