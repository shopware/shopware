<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Dbal;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\AutoIncrementField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ReferenceVersionField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StorageAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\VariantListingConfigField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\VersionField;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearcherInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\NumberRange\DataAbstractionLayer\NumberRangeField;

/**
 * Used for all search operations in the system.
 * The dbal entity searcher only joins and select fields which defined in sorting, filter or query classes.
 * Fields which are not necessary to determines which ids are affected are not fetched.
 *
 * @codeCoverageIgnore
 *
 * @see \Shopware\Tests\Integration\Core\Framework\DataAbstractionLayer\Search\EntitySearcherTest
 *
 * @internal
 */
#[Package('framework')]
class EntitySearcher implements EntitySearcherInterface
{
    public function __construct(
        private readonly Connection $connection,
        private readonly EntityDefinitionQueryHelper $queryHelper,
        private readonly CriteriaQueryBuilder $criteriaQueryBuilder
    ) {
    }

    public function search(EntityDefinition $definition, Criteria $criteria, Context $context): IdSearchResult
    {
        if ($criteria->getLimit() === 0) {
            return new IdSearchResult(0, [], $criteria, $context);
        }

        $table = $definition->getEntityName();

        $query = new QueryBuilder($this->connection);

        $fields = [];
        foreach ($definition->getFields() as $field) {
            if (!$field instanceof StorageAware || $field instanceof ReferenceVersionField || $field instanceof VersionField) {
                continue;
            }
            if ($field instanceof NumberRangeField) {
                $fields[$field->getStorageName()] = $field;

                continue;
            }
            if ($field instanceof AutoIncrementField) {
                $fields[$field->getStorageName()] = $field;

                continue;
            }
            if ($field->is(PrimaryKey::class)) {
                $fields[$field->getStorageName()] = $field;
            }
        }

        foreach ($fields as $field) {
            $query->addSelect(
                EntityDefinitionQueryHelper::escape($table) . '.' . EntityDefinitionQueryHelper::escape($field->getStorageName())
            );
        }

        $query = $this->criteriaQueryBuilder->build($query, $definition, $criteria, $context);

        if ($criteria->getIds() !== []) {
            $this->queryHelper->addIdCondition($criteria, $definition, $query);
        }

        if ($query->hasState(Criteria::SCORE_FIELD) && $criteria->getGroupFields() !== []) {
            $query = $this->buildRankedGroupingQuery($query, $definition, $criteria, $context, $table, $fields, rankByScore: true);
        } elseif ($criteria->hasState(Criteria::STATE_SORT_RANKED_GROUPING) && $criteria->getGroupFields() !== []) {
            $query = $this->buildRankedGroupingQuery($query, $definition, $criteria, $context, $table, $fields, rankByScore: false);
        } else {
            $this->queryHelper->addGroupBy($definition, $criteria, $context, $query, $table);
        }

        // add pagination
        if ($criteria->getOffset() !== null) {
            $query->setFirstResult($criteria->getOffset());
        }
        if ($criteria->getLimit() !== null) {
            $query->setMaxResults($criteria->getLimit());
        }

        $this->addTotalCountMode($criteria, $query);

        if ($criteria->getTitle()) {
            $query->setTitle($criteria->getTitle() . '::search-ids');
        }

        // execute and fetch ids
        $rows = $query->executeQuery()->fetchAllAssociative();

        $total = $this->getTotalCount($criteria, $query, $rows);

        if ($criteria->getTotalCountMode() === Criteria::TOTAL_COUNT_MODE_NEXT_PAGES) {
            $rows = \array_slice($rows, 0, $criteria->getLimit());
        }

        $converted = [];

        foreach ($rows as $row) {
            $pk = [];
            $data = [];

            foreach ($row as $storageName => $value) {
                $field = $fields[$storageName] ?? null;

                if (!$field) {
                    $data[$storageName] = $value;

                    continue;
                }

                $value = $field->getSerializer()->decode($field, $value);

                $data[$field->getPropertyName()] = $value;

                if (!$field->is(PrimaryKey::class)) {
                    continue;
                }

                $pk[$field->getPropertyName()] = $value;
            }

            $arrayKey = implode('-', $pk);

            if (\count($pk) === 1) {
                $pk = array_shift($pk);
            }

            $converted[$arrayKey] = [
                'primaryKey' => $pk,
                'data' => $data,
            ];
        }

        if ($criteria->useIdSorting()) {
            $converted = $this->sortByIdArray($criteria->getIds(), $converted);
        }

        return new IdSearchResult($total, $converted, $criteria, $context);
    }

    /**
     * Wraps a grouped query with ROW_NUMBER() OVER(PARTITION BY <group fields> ORDER BY ...), so that every group is
     * represented by exactly one deterministic row and the groups are ordered by the values of that row.
     *
     * Score ranked (`$rankByScore`): the highest-scoring row represents the group.
     * Grouping the scored query directly would aggregate the score over all entities of a group instead, so that
     * a group would score higher the more entities it contains - product variants grouped by `displayGroup` for
     * example, where a product with three variants scored three times as high as a comparable single product.
     *
     * Sort ranked ({@see Criteria::STATE_SORT_RANKED_GROUPING}): the row that decides the position of its group
     * represents the group, so `MIN()` sortings pick the row with the lowest value and `MAX()` sortings the row with the
     * highest one. Entities with a variant listing config prefer their configured main variant, which is the row a
     * product listing displays for that group.
     *
     * @param array<string, Field> $fields keyed by storage name
     */
    private function buildRankedGroupingQuery(QueryBuilder $query, EntityDefinition $definition, Criteria $criteria, Context $context, string $table, array $fields, bool $rankByScore): QueryBuilder
    {
        $primaryKeyOrder = [];
        foreach ($fields as $storageName => $field) {
            if (!$field->is(PrimaryKey::class)) {
                continue;
            }

            $primaryKeyOrder[] = 'inner_q.' . EntityDefinitionQueryHelper::escape($storageName) . ' ASC';
            $query->addGroupBy(
                EntityDefinitionQueryHelper::escape($table) . '.' . EntityDefinitionQueryHelper::escape($storageName)
            );
        }

        $partitionColumns = [];
        foreach (array_values($criteria->getGroupFields()) as $i => $grouping) {
            $accessor = $this->queryHelper->getFieldAccessor($grouping->getField(), $definition, $table, $context);
            $alias = '_group_' . $i;
            $query->addSelect($accessor . ' as `' . $alias . '`');
            $partitionColumns[] = 'inner_q.`' . $alias . '`';
        }

        $rankingOrder = [];
        if ($rankByScore) {
            $rankingOrder[] = 'inner_q._score DESC';
        } else {
            $mainVariantCondition = $this->buildMainVariantCondition($definition, $table, $context);
            if ($mainVariantCondition !== null) {
                $query->addSelect('IFNULL(' . $mainVariantCondition . ', 0) as `_main_variant`');
                $rankingOrder[] = 'inner_q.`_main_variant` DESC';
            }
        }

        $outer = new QueryBuilder($this->connection);

        foreach ($query->getOrderByPairs() as $i => [$expression, $direction]) {
            // the outer query cannot reach the table aliases the expression is built from, so it travels as a column
            $alias = Criteria::SCORE_FIELD;
            if ($expression !== Criteria::SCORE_FIELD) {
                $alias = '_sort_' . $i;
                $query->addSelect($expression . ' as ' . EntityDefinitionQueryHelper::escape($alias));
            }

            $outer->addOrderBy('ranked.' . EntityDefinitionQueryHelper::escape($alias), $direction);

            if (!$rankByScore) {
                array_push($rankingOrder, ...$this->getRankingOrder($expression, $direction, 'inner_q.' . EntityDefinitionQueryHelper::escape($alias)));
            }
        }

        $query->resetOrderBy();

        $innerSql = $query->getSQL();

        $columns = array_keys($fields);
        if ($rankByScore) {
            $columns[] = Criteria::SCORE_FIELD;
        }

        foreach ($columns as $column) {
            $outer->addSelect('ranked.' . EntityDefinitionQueryHelper::escape($column));
        }

        $outer->from(\sprintf(
            '(SELECT inner_q.*, ROW_NUMBER() OVER(PARTITION BY %s ORDER BY %s) as _rn FROM (%s) inner_q)',
            implode(', ', $partitionColumns),
            implode(', ', [...$rankingOrder, ...$primaryKeyOrder]),
            $innerSql
        ), 'ranked')->andWhere('ranked._rn = 1');

        $outer->setParameters($query->getParameters(), $query->getParameterTypes());

        return $outer;
    }

    /**
     * Ranks the rows of a group the same way the grouped sorting aggregates them: a `MIN()` sorting positions a group
     * by its lowest value, so the row with the lowest value ranks first - regardless of the sorting direction.
     *
     * @return list<string>
     */
    private function getRankingOrder(string $expression, string $direction, string $column): array
    {
        if (str_starts_with($expression, 'MIN(')) {
            // MIN() ignores NULL values, while an ascending ORDER BY would rank them first
            return [$column . ' IS NULL ASC', $column . ' ASC'];
        }

        if (str_starts_with($expression, 'MAX(')) {
            return [$column . ' DESC'];
        }

        return [$column . ' ' . $direction];
    }

    /**
     * Builds a condition which is true for the configured main variant (`variantListingConfig.mainVariantId`) of an
     * entity, unless the parent itself is displayed instead (`variantListingConfig.displayParent`).
     */
    private function buildMainVariantCondition(EntityDefinition $definition, string $table, Context $context): ?string
    {
        if (!$definition->isInheritanceAware() || !$definition->getField('variantListingConfig') instanceof VariantListingConfigField) {
            return null;
        }

        $mainVariantId = $this->queryHelper->getFieldAccessor('variantListingConfig.mainVariantId', $definition, $table, $context);
        $displayParent = $this->queryHelper->getFieldAccessor('variantListingConfig.displayParent', $definition, $table, $context);

        return \sprintf(
            '(%s = LOWER(HEX(%s.`id`)) AND IFNULL(%s, "0") NOT IN ("1", "true"))',
            $mainVariantId,
            EntityDefinitionQueryHelper::escape($table),
            $displayParent
        );
    }

    private function addTotalCountMode(Criteria $criteria, QueryBuilder $query): void
    {
        if ($criteria->getTotalCountMode() !== Criteria::TOTAL_COUNT_MODE_NEXT_PAGES || $criteria->getLimit() === null) {
            return;
        }

        $query->setMaxResults($criteria->getNextPagesLimit());
    }

    /**
     * @param list<array<string, mixed>> $data
     */
    private function getTotalCount(Criteria $criteria, QueryBuilder $query, array $data): int
    {
        if ($criteria->getTotalCountMode() !== Criteria::TOTAL_COUNT_MODE_EXACT
            && ($criteria->getTotalCountMode() !== Criteria::TOTAL_COUNT_MODE_NEXT_PAGES || $criteria->getLimit() !== null)) {
            return \count($data);
        }

        $offset = $criteria->getOffset() ?? 0;
        $isPartialPage = $criteria->getLimit() === null || \count($data) < $criteria->getLimit();
        // A partial page is the last page, so the fetched rows already determine the exact total and no separate
        // COUNT(*) query is needed. An empty page with an offset does not: the total could be anything up to the
        // offset, so fall through to the count query for that case.
        if ($isPartialPage && ($data !== [] || $offset === 0)) {
            return $offset + \count($data);
        }

        $query->resetOrderBy();
        $query->setMaxResults(null);
        $query->setFirstResult(0);

        $total = new QueryBuilder($this->connection);
        $total->select('COUNT(*)')
            ->from(\sprintf('(%s) total', $query->getSQL()))
            ->setParameters($query->getParameters(), $query->getParameterTypes());

        return (int) $total->executeQuery()->fetchOne();
    }

    /**
     * @param array<string>|array<array<string, string>> $ids
     * @param array<string, array{primaryKey: string|array<string, string>, data: array<string, mixed>}> $data
     *
     * @return array<string, array{primaryKey: string|array<string, string>, data: array<string, mixed>}>
     */
    private function sortByIdArray(array $ids, array $data): array
    {
        $sorted = [];

        foreach ($ids as $id) {
            if (\is_array($id)) {
                $id = implode('-', $id);
            }

            if (\array_key_exists($id, $data)) {
                $sorted[$id] = $data[$id];
            }
        }

        return $sorted;
    }
}
