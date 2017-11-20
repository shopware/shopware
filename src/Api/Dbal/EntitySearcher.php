<?php declare(strict_types=1);

namespace Shopware\Api\Dbal;

use Doctrine\DBAL\Connection;
use Shopware\Api\Entity\EntityDefinition;
use Shopware\Api\Search\Criteria;
use Shopware\Api\Search\EntitySearcherInterface;
use Shopware\Api\Search\Parser\SqlQueryParser;
use Shopware\Api\Search\UuidSearchResult;
use Shopware\Context\Struct\TranslationContext;

class EntitySearcher implements EntitySearcherInterface
{
    /**
     * @var Connection
     */
    private $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    public function search(string $definition, Criteria $criteria, TranslationContext $context): UuidSearchResult
    {
        /** @var EntityDefinition $definition */
        $table = $definition::getEntityName();
        $query = new QueryBuilder($this->connection);

        //add uuid select, e.g. `product`.`uuid`;
        $query->addSelect(EntityDefinitionResolver::escape($table) . '.' . EntityDefinitionResolver::escape('uuid'));

        //build from path with escaped alias, e.g. FROM product as `product`
        $query->from(
            EntityDefinitionResolver::escape($table),
            EntityDefinitionResolver::escape($table)
        );

        $fields = array_merge(
            $criteria->getSortingFields(),
            $criteria->getFilterFields(),
            $criteria->getPostFilterFields()
        );

        //join association and translated fields
        foreach ($fields as $fieldName) {
            EntityDefinitionResolver::joinField($fieldName, $definition, $table, $query, $context);
        }

        $parsed = SqlQueryParser::parse($criteria->getAllFilters(), $definition);
        if (!empty($parsed->getWheres())) {
            $query->andWhere(implode(' AND ', $parsed->getWheres()));
            foreach ($parsed->getParameters() as $key => $value) {
                $query->setParameter($key, $value, $parsed->getType($key));
            }
        }

        foreach ($criteria->getSortings() as $sorting) {
            $query->addOrderBy(
                EntityDefinitionResolver::resolveField($sorting->getField(), $definition, $definition::getEntityName()),
                $sorting->getDirection()
            );
        }
        //requires total count for query? add save SQL_CALC_FOUND_ROWS
        if ($criteria->fetchCount()) {
            $selects = $query->getQueryPart('select');
            $selects[0] = 'SQL_CALC_FOUND_ROWS ' . $selects[0];
            $query->select($selects);
        }

        //add pagination
        if ($criteria->getOffset() >= 0) {
            $query->setFirstResult($criteria->getOffset());
        }
        if ($criteria->getLimit() >= 0) {
            $query->setMaxResults($criteria->getLimit());
        }

        if ($query->hasState(EntityDefinitionResolver::HAS_TO_MANY_JOIN)) {
            $query->addGroupBy(
                EntityDefinitionResolver::escape($table) . '.' . EntityDefinitionResolver::escape('uuid')
            );

            // each order by column has to be inside the group by statement (sql_mode=only_full_group_by)
            foreach ($criteria->getSortings() as $sorting) {
                $field = EntityDefinitionResolver::resolveField($sorting->getField(), $definition, $definition::getEntityName());
                $query->addGroupBy($field);
            }
        }

        //execute and fetch uuids
        $uuids = $query->execute()->fetchAll(\PDO::FETCH_COLUMN);

        if ($criteria->fetchCount()) {
            $total = (int) $this->connection->fetchColumn('SELECT FOUND_ROWS()');
        } else {
            $total = count($uuids);
        }

        return new UuidSearchResult($total, $uuids, $criteria, $context);
    }
}
