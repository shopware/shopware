<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Dbal;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Exception\InvalidSortingDirectionException;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Parser\SqlQueryParser;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Query\ScoreQuery;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Term\EntityScoreQueryBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Term\SearchTermInterpreter;
use Shopware\Core\Framework\Uuid\Uuid;

trait CriteriaQueryHelper
{
    abstract protected function getParser(): SqlQueryParser;

    abstract protected function getDefinitionHelper(): EntityDefinitionQueryHelper;

    abstract protected function getInterpreter(): SearchTermInterpreter;

    abstract protected function getScoreBuilder(): EntityScoreQueryBuilder;

    protected function buildQueryByCriteria(QueryBuilder $query, EntityDefinition $definition, Criteria $criteria, Context $context): QueryBuilder
    {
        $table = $definition->getEntityName();

        $query = $this->getDefinitionHelper()->getBaseQuery($query, $definition, $context);

        if ($definition->isInheritanceAware() && $context->considerInheritance()) {
            $parent = $definition->getFields()->get('parent');
            $this->getDefinitionHelper()->resolveField($parent, $definition, $definition->getEntityName(), $query, $context);
        }

        if ($criteria->getTerm()) {
            $pattern = $this->getInterpreter()->interpret($criteria->getTerm());
            $queries = $this->getScoreBuilder()->buildScoreQueries($pattern, $definition, $definition->getEntityName());
            $criteria->addQuery(...$queries);
        }

        $fields = $this->getFieldsByCriteria($criteria);

        //join association and translated fields
        foreach ($fields as $fieldName) {
            if ($fieldName === '_score') {
                continue;
            }
            $this->getDefinitionHelper()->resolveAccessor($fieldName, $definition, $table, $query, $context);
        }

        $this->addFilters($definition, $criteria, $query, $context);

        $this->addQueries($definition, $criteria, $query, $context);

        $this->addSortings($definition, $criteria, $query, $context);

        return $query;
    }

    protected function addIdCondition(Criteria $criteria, EntityDefinition $definition, QueryBuilder $query): void
    {
        $primaryKey = $criteria->getIds();

        $primaryKey = array_values($primaryKey);

        if (!\is_array($primaryKey[0]) || \count($primaryKey[0]) === 1) {
            $bytes = array_map(function (string $id) {
                return Uuid::fromHexToBytes($id);
            }, $criteria->getIds());

            $query->andWhere(EntityDefinitionQueryHelper::escape($definition->getEntityName()) . '.`id` IN (:ids)');

            $query->setParameter('ids', array_values($bytes), Connection::PARAM_STR_ARRAY);

            return;
        }

        $this->addIdConditionWithOr($criteria, $definition, $query);
    }

    private function addIdConditionWithOr(Criteria $criteria, EntityDefinition $definition, QueryBuilder $query): void
    {
        $wheres = [];

        foreach ($criteria->getIds() as $primaryKey) {
            if (!is_array($primaryKey)) {
                $primaryKey = ['id' => $primaryKey];
            }

            $where = [];

            foreach ($primaryKey as $storageName => $value) {
                $field = $definition->getFields()->getByStorageName($storageName);

                if ($field instanceof IdField || $field instanceof FkField) {
                    $value = Uuid::fromHexToBytes($value);
                }

                $key = 'pk' . Uuid::randomHex();

                $accessor = EntityDefinitionQueryHelper::escape($definition->getEntityName()) . '.' . EntityDefinitionQueryHelper::escape($storageName);

                $where[] = $accessor . ' = :' . $key;

                $query->setParameter($key, $value);
            }

            $wheres[] = '(' . implode(' AND ', $where) . ')';
        }

        $wheres = implode(' OR ', $wheres);

        $query->andWhere($wheres);
    }

    private function addFilters(EntityDefinition $definition, Criteria $criteria, QueryBuilder $query, Context $context): void
    {
        $filters = new MultiFilter(
            MultiFilter::CONNECTION_AND,
            array_merge(
                $criteria->getFilters(),
                $criteria->getPostFilters()
            )
        );

        $parsed = $this->getParser()->parse($filters, $definition, $context);

        if (empty($parsed->getWheres())) {
            return;
        }

        $query->andWhere(implode(' AND ', $parsed->getWheres()));
        foreach ($parsed->getParameters() as $key => $value) {
            $query->setParameter($key, $value, $parsed->getType($key));
        }
    }

    private function addQueries(EntityDefinition $definition, Criteria $criteria, QueryBuilder $query, Context $context): void
    {
        $queries = $this->getParser()->parseRanking(
            $criteria->getQueries(),
            $definition,
            $definition->getEntityName(),
            $context
        );
        if (empty($queries->getWheres())) {
            return;
        }

        $query->addState(EntityDefinitionQueryHelper::HAS_TO_MANY_JOIN);

        $select = 'SUM(' . implode(' + ', $queries->getWheres()) . ')';
        $query->addSelect($select . ' as _score');

        if (empty($criteria->getSorting())) {
            $query->addOrderBy('_score', 'DESC');
        }

        $minScore = array_map(function (ScoreQuery $query) {
            return $query->getScore();
        }, $criteria->getQueries());

        $minScore = min($minScore);

        $query->andHaving('_score >= :_minScore');
        $query->setParameter('_minScore', $minScore);
        $query->addState('_score');

        foreach ($queries->getParameters() as $key => $value) {
            $query->setParameter($key, $value, $queries->getType($key));
        }
    }

    private function addSortings(EntityDefinition $definition, Criteria $criteria, QueryBuilder $query, Context $context): void
    {
        foreach ($criteria->getSorting() as $sorting) {
            $this->validateSortingDirection($sorting->getDirection());

            if ($sorting->getField() === '_score') {
                $query->addOrderBy('_score', $sorting->getDirection());
                $query->addState('_score');
                continue;
            }

            $accessor = $this->getDefinitionHelper()->getFieldAccessor($sorting->getField(), $definition, $definition->getEntityName(), $context);

            if ($sorting->getNaturalSorting()) {
                $query->addOrderBy('LENGTH(' . $accessor . ')', $sorting->getDirection());
            }

            $query->addOrderBy($accessor, $sorting->getDirection());
        }
    }

    /**
     * @return string[]
     */
    private function getFieldsByCriteria(Criteria $criteria): array
    {
        $fields = [];

        foreach ($criteria->getSorting() as $field) {
            $fields[] = $field->getFields();
        }

        foreach ($criteria->getFilters() as $field) {
            $fields[] = $field->getFields();
        }

        foreach ($criteria->getPostFilters() as $field) {
            $fields[] = $field->getFields();
        }

        foreach ($criteria->getQueries() as $field) {
            $fields[] = $field->getFields();
        }

        if (count($fields) === 0) {
            return [];
        }

        return array_merge(...$fields);
    }

    /**
     * @throws InvalidSortingDirectionException
     */
    private function validateSortingDirection(string $direction): void
    {
        if (!in_array(strtoupper($direction), [FieldSorting::ASCENDING, FieldSorting::DESCENDING], true)) {
            throw new InvalidSortingDirectionException($direction);
        }
    }
}
