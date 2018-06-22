<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ORM\Dbal;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\ORM\EntityDefinition;
use Shopware\Core\Framework\ORM\Search\Criteria;
use Shopware\Core\Framework\ORM\Search\Parser\SqlQueryParser;
use Shopware\Core\Framework\ORM\Search\Query\ScoreQuery;

trait CriteriaQueryHelper
{
    protected function buildQueryByCriteria(QueryBuilder $query, EntityDefinitionQueryHelper $queryHelper, SqlQueryParser $parser, string $definition, Criteria $criteria, Context $context): QueryBuilder
    {
        /** @var EntityDefinition $definition */
        $table = $definition::getEntityName();

        $query = $queryHelper->getBaseQuery($query, $definition, $context);

        if ($definition::isInheritanceAware()) {
            /** @var EntityDefinition|string $definition */
            $parent = $definition::getFields()->get('parent');
            $queryHelper->resolveField($parent, $definition, $definition::getEntityName(), $query, $context);
        }

        $fields = array_merge(
            $criteria->getSortingFields(),
            $criteria->getFilterFields(),
            $criteria->getPostFilterFields(),
            $criteria->getQueryFields()
        );

        //join association and translated fields
        foreach ($fields as $fieldName) {
            if ($fieldName === '_score') {
                continue;
            }
            $queryHelper->resolveAccessor($fieldName, $definition, $table, $query, $context);
        }

        $this->addFilters($parser, $definition, $criteria, $query, $context);

        $this->addQueries($parser, $definition, $criteria, $query, $context);

        $this->addSortings($queryHelper, $definition, $criteria, $query, $context);

        return $query;
    }

    protected function addFilters(SqlQueryParser $parser, string $definition, Criteria $criteria, QueryBuilder $query, Context $context): void
    {
        $parsed = $parser->parse($criteria->getAllFilters(), $definition, $context);

        if (empty($parsed->getWheres())) {
            return;
        }

        $query->andWhere(implode(' AND ', $parsed->getWheres()));
        foreach ($parsed->getParameters() as $key => $value) {
            $query->setParameter($key, $value, $parsed->getType($key));
        }
    }

    protected function addQueries(SqlQueryParser $parser, string $definition, Criteria $criteria, QueryBuilder $query, Context $context): void
    {
        /** @var string|EntityDefinition $definition */
        $queries = $parser->parseRanking(
            $criteria->getQueries(),
            $definition,
            $definition::getEntityName(),
            $context
        );
        if (empty($queries->getWheres())) {
            return;
        }

        $query->addState(EntityDefinitionQueryHelper::HAS_TO_MANY_JOIN);

        $select = 'SUM(' . implode(' + ', $queries->getWheres()) . ')';
        $query->addSelect($select . ' as _score');

        if (empty($criteria->getSortings())) {
            $query->addOrderBy('_score', 'DESC');
        }

        $minScore = array_map(function (ScoreQuery $query) {
            return $query->getScore();
        }, $criteria->getQueries());

        $minScore = min($minScore);

        $query->andHaving('_score >= :_minScore');
        $query->setParameter('_minScore', $minScore);

        foreach ($queries->getParameters() as $key => $value) {
            $query->setParameter($key, $value, $queries->getType($key));
        }
    }

    protected function addSortings(EntityDefinitionQueryHelper $queryHelper, string $definition, Criteria $criteria, QueryBuilder $query, Context $context): void
    {
        /* @var string|EntityDefinition $definition */
        foreach ($criteria->getSortings() as $sorting) {
            if ($sorting->getField() === '_score') {
                $query->addOrderBy('_score', $sorting->getDirection());
                continue;
            }

            $query->addOrderBy(
                $queryHelper->getFieldAccessor(
                    $sorting->getField(),
                    $definition,
                    $definition::getEntityName(),
                    $context
                ),
                $sorting->getDirection()
            );
        }
    }

    protected function addGroupBy(EntityDefinitionQueryHelper $queryHelper, string $definition, Criteria $criteria, QueryBuilder $query, Context $context): void
    {
        /** @var string|EntityDefinition $definition */
        $table = $definition::getEntityName();

        if (!$query->hasState(EntityDefinitionQueryHelper::HAS_TO_MANY_JOIN)) {
            return;
        }

        $fields = [
            EntityDefinitionQueryHelper::escape($table) . '.' . EntityDefinitionQueryHelper::escape('id'),
        ];

        // each order by column has to be inside the group by statement (sql_mode=only_full_group_by)
        foreach ($criteria->getSortings() as $sorting) {
            if ($sorting->getField() === '_score') {
                continue;
            }

            $fields[] = $queryHelper->getFieldAccessor(
                $sorting->getField(),
                $definition,
                $definition::getEntityName(),
                $context
            );
        }

        $fields = array_unique($fields);

        foreach ($fields as $field) {
            $query->addGroupBy($field);
        }
    }
}
