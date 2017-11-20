<?php declare(strict_types=1);

namespace Shopware\Api\Dbal;

use Doctrine\DBAL\Connection;
use Shopware\Api\Entity\EntityDefinition;
use Shopware\Api\Search\Aggregation\Aggregation;
use Shopware\Api\Search\Aggregation\AvgAggregation;
use Shopware\Api\Search\Aggregation\CardinalityAggregation;
use Shopware\Api\Search\Aggregation\EntityAggregation;
use Shopware\Api\Search\Aggregation\MaxAggregation;
use Shopware\Api\Search\Aggregation\MinAggregation;
use Shopware\Api\Search\Aggregation\StatsAggregation;
use Shopware\Api\Search\Aggregation\SumAggregation;
use Shopware\Api\Search\Aggregation\ValueCountAggregation;
use Shopware\Api\Search\AggregationResult;
use Shopware\Api\Search\Criteria;
use Shopware\Api\Search\EntityAggregatorInterface;
use Shopware\Api\Search\Parser\SqlQueryParser;
use Shopware\Context\Struct\TranslationContext;

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

    public function __construct(Connection $connection, EntityReader $reader)
    {
        $this->connection = $connection;
        $this->reader = $reader;
    }

    public function aggregate(string $definition, Criteria $criteria, TranslationContext $context): AggregationResult
    {
        $aggregations = [];
        foreach ($criteria->getAggregations() as $aggregation) {
            $query = $this->createAggregationQuery($aggregation, $definition, $criteria, $context);

            $aggregations[$aggregation->getName()] = $this->fetchAggregation($definition, $query, $aggregation, $context);
        }

        return new AggregationResult($aggregations, $context, $criteria);
    }

    private function createAggregationQuery(Aggregation $aggregation, string $definition, Criteria $criteria, TranslationContext $context): QueryBuilder
    {
        /** @var EntityDefinition $definition */
        $table = $definition::getEntityName();
        $query = new QueryBuilder($this->connection);

        //build from path with escaped alias, e.g. FROM product as `product`
        $query->from($table, EntityDefinitionResolver::escape($table));

        $fields = array_merge(
            $criteria->getFilterFields(),
            $aggregation->getFields()
        );

        //join association and translated fields
        foreach ($fields as $fieldName) {
            EntityDefinitionResolver::joinField($fieldName, $definition, $table, $query, $context);
        }

        $parsed = SqlQueryParser::parse($criteria->getFilters(), $definition);
        if (!empty($parsed->getWheres())) {
            $query->andWhere(implode(' AND ', $parsed->getWheres()));
            foreach ($parsed->getParameters() as $key => $value) {
                $query->setParameter($key, $value, $parsed->getType($key));
            }
        }

        return $query;
    }

    private function fetchAggregation(string $definition, QueryBuilder $query, Aggregation $aggregation, TranslationContext $context)
    {
        /** @var EntityDefinition $definition */
        $field = EntityDefinitionResolver::resolveField(
            $aggregation->getField(),
            $definition,
            $definition::getEntityName()
        );

        if ($aggregation instanceof EntityAggregation) {
            $query->select([$field]);
            $query->groupBy($field);

            $uuids = $query->execute()->fetchAll(\PDO::FETCH_COLUMN);

            return $this->reader->readBasic($aggregation->getDefinition(), $uuids, $context);
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
