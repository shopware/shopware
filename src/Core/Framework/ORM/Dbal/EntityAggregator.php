<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ORM\Dbal;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\ORM\EntityDefinition;
use Shopware\Core\Framework\ORM\Read\EntityReaderInterface;
use Shopware\Core\Framework\ORM\Read\ReadCriteria;
use Shopware\Core\Framework\ORM\Search\Aggregation\Aggregation;
use Shopware\Core\Framework\ORM\Search\Aggregation\AggregationResult;
use Shopware\Core\Framework\ORM\Search\Aggregation\AggregationResultCollection;
use Shopware\Core\Framework\ORM\Search\Aggregation\AvgAggregation;
use Shopware\Core\Framework\ORM\Search\Aggregation\CardinalityAggregation;
use Shopware\Core\Framework\ORM\Search\Aggregation\CountAggregation;
use Shopware\Core\Framework\ORM\Search\Aggregation\EntityAggregation;
use Shopware\Core\Framework\ORM\Search\Aggregation\MaxAggregation;
use Shopware\Core\Framework\ORM\Search\Aggregation\MinAggregation;
use Shopware\Core\Framework\ORM\Search\Aggregation\StatsAggregation;
use Shopware\Core\Framework\ORM\Search\Aggregation\SumAggregation;
use Shopware\Core\Framework\ORM\Search\Aggregation\ValueCountAggregation;
use Shopware\Core\Framework\ORM\Search\AggregatorResult;
use Shopware\Core\Framework\ORM\Search\Criteria;
use Shopware\Core\Framework\ORM\Search\EntityAggregatorInterface;
use Shopware\Core\Framework\ORM\Search\Parser\SqlQueryParser;
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
     * @var EntityReader
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

            $value = $this->fetchAggregation($definition, $query, $aggregation, $context);

            $aggregations->add(
                new AggregationResult($aggregation, $value)
            );
        }

        return new AggregatorResult($aggregations, $context, $criteria);
    }

    private function createAggregationQuery(Aggregation $aggregation, string $definition, Criteria $criteria, Context $context): QueryBuilder
    {
        /** @var EntityDefinition $definition */
        $table = $definition::getEntityName();

        $query = $this->queryHelper->getBaseQuery($this->connection, $definition, $context);

        $fields = array_merge(
            $criteria->getFilterFields(),
            $aggregation->getFields()
        );

        //join association and translated fields
        foreach ($fields as $fieldName) {
            $this->queryHelper->resolveAccessor($fieldName, $definition, $table, $query, $context);
        }

        $parent = null;

        if ($definition::isInheritanceAware()) {
            /** @var EntityDefinition|string $definition */
            $parent = $definition::getFields()->get('parent');
            $this->queryHelper->resolveField($parent, $definition, $table, $query, $context);
        }

        $parsed = $this->queryParser->parse($criteria->getFilters(), $definition, $context);
        if (!empty($parsed->getWheres())) {
            $query->andWhere(implode(' AND ', $parsed->getWheres()));
            foreach ($parsed->getParameters() as $key => $value) {
                $query->setParameter($key, $value, $parsed->getType($key));
            }
        }

        return $query;
    }

    private function fetchAggregation(string $definition, QueryBuilder $query, Aggregation $aggregation, Context $context)
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

            $ids = $query->execute()->fetchAll(\PDO::FETCH_COLUMN);
            $ids = array_filter($ids);

            $ids = array_map(function ($bytes) {
                return Uuid::fromBytesToHex($bytes);
            }, $ids);

            return $this->reader->read($aggregation->getDefinition(), new ReadCriteria($ids), $context);
        }

        if ($aggregation instanceof ValueCountAggregation) {
            $query->select([
                $accessor . ' as `key`',
                'COUNT(' . $accessor . ')' . ' as `count`',
            ]);
            $query->groupBy($accessor);

            return $query->execute()->fetchAll(\PDO::FETCH_ASSOC);
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

            return $query->execute()->fetch(\PDO::FETCH_ASSOC);
        }

        if ($aggregation instanceof StatsAggregation) {
            $query->select([
                'COUNT(' . $accessor . ')' . ' as `count`',
                'AVG(' . $accessor . ')' . ' as `avg`',
                'SUM(' . $accessor . ')' . ' as `sum`',
                'MIN(' . $accessor . ')' . ' as `min`',
                'MAX(' . $accessor . ')' . ' as `max`',
            ]);

            return $query->execute()->fetch(\PDO::FETCH_ASSOC);
        }

        if ($aggregation instanceof CardinalityAggregation) {
            $query->select([$accessor]);
            $query->groupBy($accessor);

            return $query->execute()->fetchAll(\PDO::FETCH_COLUMN);
        }

        if ($aggregation instanceof AvgAggregation) {
            $query->select('AVG(' . $accessor . ') as `avg`');

            return $query->execute()->fetch(\PDO::FETCH_ASSOC);
        }

        if ($aggregation instanceof MaxAggregation) {
            $query->select('MAX(' . $accessor . ') as `max`');

            return $query->execute()->fetch(\PDO::FETCH_ASSOC);
        }

        if ($aggregation instanceof CountAggregation) {
            $query->select('COUNT(' . $accessor . ') as `count`');

            return $query->execute()->fetch(\PDO::FETCH_ASSOC);
        }

        if ($aggregation instanceof MinAggregation) {
            $query->select('MIN(' . $accessor . ') as `min`');

            return $query->execute()->fetch(\PDO::FETCH_ASSOC);
        }

        if ($aggregation instanceof SumAggregation) {
            $query->select('SUM(' . $accessor . ') as `sum`');

            return $query->execute()->fetch(\PDO::FETCH_ASSOC);
        }

        throw new \RuntimeException(
            sprintf('Aggregation of type %s not supported', get_class($aggregation))
        );
    }
}
