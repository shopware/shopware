<?php declare(strict_types=1);

namespace Shopware\Api\Entity\Dbal;

use Doctrine\DBAL\Connection;
use Shopware\Api\Entity\EntityDefinition;
use Shopware\Api\Entity\Read\EntityReaderInterface;
use Shopware\Api\Entity\Search\Aggregation\Aggregation;
use Shopware\Api\Entity\Search\Aggregation\AvgAggregation;
use Shopware\Api\Entity\Search\Aggregation\CardinalityAggregation;
use Shopware\Api\Entity\Search\Aggregation\EntityAggregation;
use Shopware\Api\Entity\Search\Aggregation\MaxAggregation;
use Shopware\Api\Entity\Search\Aggregation\MinAggregation;
use Shopware\Api\Entity\Search\Aggregation\StatsAggregation;
use Shopware\Api\Entity\Search\Aggregation\SumAggregation;
use Shopware\Api\Entity\Search\Aggregation\ValueCountAggregation;
use Shopware\Api\Entity\Search\AggregationResult;
use Shopware\Api\Entity\Search\Criteria;
use Shopware\Api\Entity\Search\EntityAggregatorInterface;
use Shopware\Api\Entity\Search\Parser\SqlQueryParser;
use Shopware\Context\Struct\ShopContext;

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

    public function aggregate(string $definition, Criteria $criteria, ShopContext $context): AggregationResult
    {
        $aggregations = [];
        foreach ($criteria->getAggregations() as $aggregation) {
            $query = $this->createAggregationQuery($aggregation, $definition, $criteria, $context);

            $aggregations[$aggregation->getName()] = $this->fetchAggregation($definition, $query, $aggregation, $context);
        }

        return new AggregationResult($aggregations, $context, $criteria);
    }

    private function createAggregationQuery(Aggregation $aggregation, string $definition, Criteria $criteria, ShopContext $context): QueryBuilder
    {
        /** @var EntityDefinition $definition */
        $table = $definition::getEntityName();
        $query = new QueryBuilder($this->connection);

        //build from path with escaped alias, e.g. FROM product as `product`
        $query->from($table, EntityDefinitionQueryHelper::escape($table));

        $fields = array_merge(
            $criteria->getFilterFields(),
            $aggregation->getFields()
        );

        //join association and translated fields
        foreach ($fields as $fieldName) {
            EntityDefinitionQueryHelper::joinField($fieldName, $definition, $table, $query, $context);
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
        /** @var EntityDefinition $definition */
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
