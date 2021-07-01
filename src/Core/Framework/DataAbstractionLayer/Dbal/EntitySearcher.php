<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Dbal;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ReferenceVersionField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StorageAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\VersionField;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearcherInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;

/**
 * Used for all search operations in the system.
 * The dbal entity searcher only joins and select fields which defined in sorting, filter or query classes.
 * Fields which are not necessary to determines which ids are affected are not fetched.
 */
class EntitySearcher implements EntitySearcherInterface
{
    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var EntityDefinitionQueryHelper
     */
    private $queryHelper;

    /**
     * @var CriteriaQueryBuilder
     */
    private $criteriaQueryBuilder;

    public function __construct(
        Connection $connection,
        EntityDefinitionQueryHelper $queryHelper,
        CriteriaQueryBuilder $criteriaQueryBuilder
    ) {
        $this->connection = $connection;
        $this->queryHelper = $queryHelper;
        $this->criteriaQueryBuilder = $criteriaQueryBuilder;
    }

    public function search(EntityDefinition $definition, Criteria $criteria, Context $context): IdSearchResult
    {
        $table = $definition->getEntityName();

        $fields = $definition->getPrimaryKeys();

        $query = new QueryBuilder($this->connection);

        foreach ($fields as $field) {
            if ($field instanceof ReferenceVersionField || $field instanceof VersionField) {
                continue;
            }
            if (!$field instanceof StorageAware) {
                continue;
            }

            $query->addSelect(
                EntityDefinitionQueryHelper::escape($table) . '.' . EntityDefinitionQueryHelper::escape($field->getStorageName())
            );
        }

        $query = $this->criteriaQueryBuilder->build($query, $definition, $criteria, $context);

        if (!empty($criteria->getIds())) {
            $this->queryHelper->addIdCondition($criteria, $definition, $query);
        }

        $this->addGroupBy($definition, $criteria, $context, $query, $table);

        //add pagination
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

        //execute and fetch ids
        $rows = $query->execute()->fetchAll();

        $total = $this->getTotalCount($criteria, $query, $rows);

        if ($criteria->getTotalCountMode() === Criteria::TOTAL_COUNT_MODE_NEXT_PAGES) {
            $rows = \array_slice($rows, 0, $criteria->getLimit());
        }

        $converted = [];

        foreach ($rows as $row) {
            $pk = [];
            $data = [];

            foreach ($row as $storageName => $value) {
                $field = $fields->getByStorageName($storageName);

                if ($field) {
                    $value = $field->getSerializer()->decode($field, $value);

                    $pk[$storageName] = $value;
                }

                $data[$storageName] = $value;
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

    private function addTotalCountMode(Criteria $criteria, QueryBuilder $query): void
    {
        if ($criteria->getTotalCountMode() !== Criteria::TOTAL_COUNT_MODE_NEXT_PAGES) {
            return;
        }

        $query->setMaxResults($criteria->getLimit() * 6 + 1);
    }

    private function getTotalCount(Criteria $criteria, QueryBuilder $query, array $data): int
    {
        if ($criteria->getTotalCountMode() !== Criteria::TOTAL_COUNT_MODE_EXACT) {
            return \count($data);
        }

        $query->setMaxResults(null);
        $query->setFirstResult(null);

        $total = new QueryBuilder($query->getConnection());
        $total->select(['COUNT(*)'])
            ->from(sprintf('(%s) total', $query->getSQL()))
            ->setParameters($query->getParameters(), $query->getParameterTypes());

        return (int) $total->execute()->fetchOne();
    }

    private function addGroupBy(EntityDefinition $definition, Criteria $criteria, Context $context, QueryBuilder $query, string $table): void
    {
        if ($criteria->getGroupFields()) {
            foreach ($criteria->getGroupFields() as $grouping) {
                $accessor = $this->queryHelper->getFieldAccessor($grouping->getField(), $definition, $definition->getEntityName(), $context);

                $query->addGroupBy($accessor);
            }

            return;
        }

        if ($query->hasState(EntityDefinitionQueryHelper::HAS_TO_MANY_JOIN)) {
            $query->addGroupBy(
                EntityDefinitionQueryHelper::escape($table) . '.' . EntityDefinitionQueryHelper::escape('id')
            );
        }
    }

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
