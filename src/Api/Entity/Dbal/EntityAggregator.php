<?php declare(strict_types=1);

namespace Shopware\Api\Entity\Dbal;

use Doctrine\DBAL\Connection;
use Shopware\Api\Entity\EntityDefinition;
use Shopware\Api\Entity\Read\EntityReaderInterface;
use Shopware\Api\Entity\Search\Aggregation\Aggregation;
use Shopware\Api\Entity\Search\Aggregation\AggregationResult;
use Shopware\Api\Entity\Search\Aggregation\AggregationResultCollection;
use Shopware\Api\Entity\Search\Aggregation\AvgAggregation;
use Shopware\Api\Entity\Search\Aggregation\CardinalityAggregation;
use Shopware\Api\Entity\Search\Aggregation\EntityAggregation;
use Shopware\Api\Entity\Search\Aggregation\MaxAggregation;
use Shopware\Api\Entity\Search\Aggregation\MinAggregation;
use Shopware\Api\Entity\Search\Aggregation\StatsAggregation;
use Shopware\Api\Entity\Search\Aggregation\SumAggregation;
use Shopware\Api\Entity\Search\Aggregation\ValueCountAggregation;
use Shopware\Api\Entity\Search\AggregatorResult;
use Shopware\Api\Entity\Search\Criteria;
use Shopware\Api\Entity\Search\EntityAggregatorInterface;
use Shopware\Api\Entity\Search\Parser\SqlQueryParser;
use Shopware\Api\Entity\Write\Flag\Inherited;
use Shopware\Api\Product\Definition\ProductDefinition;
use Shopware\Context\Struct\ApplicationContext;
use Shopware\Framework\Struct\Uuid;

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

    public function aggregate(string $definition, Criteria $criteria, ApplicationContext $context): AggregatorResult
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

    private function createAggregationQuery(Aggregation $aggregation, string $definition, Criteria $criteria, ApplicationContext $context): QueryBuilder
    {
        /** @var EntityDefinition $definition */
        $table = $definition::getEntityName();

        $query = $this->queryHelper->getBaseQuery($this->connection, ProductDefinition::class, $context);

        $fields = array_merge(
            $criteria->getFilterFields(),
            $aggregation->getFields()
        );

        //join association and translated fields
        foreach ($fields as $fieldName) {
            $this->queryHelper->resolveAccessor($fieldName, $definition, $table, $query, $context);
        }

        $parent = null;

        if ($definition::getParentPropertyName()) {
            /** @var EntityDefinition|string $definition */
            $parent = $definition::getFields()->get($definition::getParentPropertyName());
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

    private function fetchAggregation(string $definition, QueryBuilder $query, Aggregation $aggregation, ApplicationContext $context)
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

            return $this->reader->readBasic($aggregation->getDefinition(), $ids, $context);
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
            $query->select('AVG(' . $accessor . ')');

            return $query->execute()->fetch(\PDO::FETCH_COLUMN);
        }

        if ($aggregation instanceof MaxAggregation) {
            $query->select('MAX(' . $accessor . ')');

            return $query->execute()->fetch(\PDO::FETCH_COLUMN);
        }

        if ($aggregation instanceof MinAggregation) {
            $query->select('MIN(' . $accessor . ')');

            return $query->execute()->fetch(\PDO::FETCH_COLUMN);
        }

        if ($aggregation instanceof SumAggregation) {
            $query->select('SUM(' . $accessor . ')');

            return $query->execute()->fetch(\PDO::FETCH_COLUMN);
        }

        throw new \RuntimeException(
            sprintf('Aggregation of type %s not supported', get_class($aggregation))
        );
    }
}
