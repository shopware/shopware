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
use Shopware\Api\Product\Definition\ProductDefinition;
use Shopware\Context\Struct\ShopContext;
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

    public function __construct(Connection $connection, EntityReaderInterface $reader)
    {
        $this->connection = $connection;
        $this->reader = $reader;
    }

    public function aggregate(string $definition, Criteria $criteria, ShopContext $context): AggregatorResult
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

    private function createAggregationQuery(Aggregation $aggregation, string $definition, Criteria $criteria, ShopContext $context): QueryBuilder
    {
        /** @var EntityDefinition $definition */
        $table = $definition::getEntityName();

        $query = EntityDefinitionQueryHelper::getBaseQuery($this->connection, ProductDefinition::class, $context);

        $fields = array_merge(
            $criteria->getFilterFields(),
            $aggregation->getFields()
        );

        //join association and translated fields
        foreach ($fields as $fieldName) {
            EntityDefinitionQueryHelper::joinField($fieldName, $definition, $table, $query, $context);
        }

        $parent = null;

        if ($definition::getParentPropertyName()) {
            /** @var EntityDefinition|string $definition */
            $parent = $definition::getFields()->get($definition::getParentPropertyName());
            EntityDefinitionQueryHelper::joinManyToOne($definition, $table, $parent, $query, $context);
        }

        $parsed = SqlQueryParser::parse($criteria->getFilters(), $definition, $context);
        if (!empty($parsed->getWheres())) {
            $query->andWhere(implode(' AND ', $parsed->getWheres()));
            foreach ($parsed->getParameters() as $key => $value) {
                $query->setParameter($key, $value, $parsed->getType($key));
            }
        }

        return $query;
    }

    private function fetchAggregation(string $definition, QueryBuilder $query, Aggregation $aggregation, ShopContext $context)
    {
        /** @var EntityDefinition|string $definition */
        $field = EntityDefinitionQueryHelper::getFieldAccessor(
            $aggregation->getField(),
            $definition,
            $definition::getEntityName(),
            $context
        );

        if ($aggregation instanceof EntityAggregation) {
            $query->select([$field]);
            $query->groupBy($field);

            $ids = $query->execute()->fetchAll(\PDO::FETCH_COLUMN);
            $ids = array_filter($ids);

            $ids = array_map(function ($bytes) {
                return Uuid::fromBytesToHex($bytes);
            }, $ids);

            return $this->reader->readBasic($aggregation->getDefinition(), $ids, $context);
        }

        if ($aggregation instanceof ValueCountAggregation) {
            $query->select([
                $field . ' as `key`',
                'COUNT(' . $field . ')' . ' as `count`',
            ]);
            $query->groupBy($field);

            return $query->execute()->fetchAll(\PDO::FETCH_ASSOC);
        }

        if ($aggregation instanceof StatsAggregation) {
            $select = [];
            if ($aggregation->fetchCount()) {
                $select[] = 'COUNT(' . $field . ')' . ' as `count`';
            }
            if ($aggregation->fetchAvg()) {
                $select[] = 'AVG(' . $field . ')' . ' as `avg`';
            }
            if ($aggregation->fetchSum()) {
                $select[] = 'SUM(' . $field . ')' . ' as `sum`';
            }
            if ($aggregation->fetchMin()) {
                $select[] = 'MIN(' . $field . ')' . ' as `min`';
            }
            if ($aggregation->fetchMax()) {
                $select[] = 'MAX(' . $field . ')' . ' as `max`';
            }

            if (empty($select)) {
                throw new \RuntimeException('StatsAggregation configured without fetch');
            }

            $query->select($select);

            return $query->execute()->fetch(\PDO::FETCH_ASSOC);
        }

        if ($aggregation instanceof StatsAggregation) {
            $query->select([
                'COUNT(' . $field . ')' . ' as `count`',
                'AVG(' . $field . ')' . ' as `avg`',
                'SUM(' . $field . ')' . ' as `sum`',
                'MIN(' . $field . ')' . ' as `min`',
                'MAX(' . $field . ')' . ' as `max`',
            ]);

            return $query->execute()->fetch(\PDO::FETCH_ASSOC);
        }

        if ($aggregation instanceof CardinalityAggregation) {
            $query->select([$field]);
            $query->groupBy($field);

            return $query->execute()->fetchAll(\PDO::FETCH_COLUMN);
        }

        if ($aggregation instanceof AvgAggregation) {
            $query->select('AVG(' . $field . ')');

            return $query->execute()->fetch(\PDO::FETCH_COLUMN);
        }

        if ($aggregation instanceof MaxAggregation) {
            $query->select('MAX(' . $field . ')');

            return $query->execute()->fetch(\PDO::FETCH_COLUMN);
        }

        if ($aggregation instanceof MinAggregation) {
            $query->select('MIN(' . $field . ')');

            return $query->execute()->fetch(\PDO::FETCH_COLUMN);
        }

        if ($aggregation instanceof SumAggregation) {
            $query->select('SUM(' . $field . ')');

            return $query->execute()->fetch(\PDO::FETCH_COLUMN);
        }

        throw new \RuntimeException(
            sprintf('Aggregation of type %s not supported', get_class($aggregation))
        );
    }
}
