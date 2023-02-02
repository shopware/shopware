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
use Shopware\Core\Framework\DataAbstractionLayer\Field\VersionField;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearcherInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Shopware\Core\Framework\Feature;
use Shopware\Core\System\NumberRange\DataAbstractionLayer\NumberRangeField;

/**
 * Used for all search operations in the system.
 * The dbal entity searcher only joins and select fields which defined in sorting, filter or query classes.
 * Fields which are not necessary to determines which ids are affected are not fetched.
 *
 * @deprecated tag:v6.5.0 - reason:becomes-internal - Will be internal
 */
class EntitySearcher implements EntitySearcherInterface
{
    private Connection $connection;

    private EntityDefinitionQueryHelper $queryHelper;

    private CriteriaQueryBuilder $criteriaQueryBuilder;

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

        /** @var StorageAware $field */
        foreach ($fields as $field) {
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
                $field = $fields[$storageName] ?? null;

                if (!$field) {
                    $data[$storageName] = $value;

                    continue;
                }

                $value = $field->getSerializer()->decode($field, $value);

                // @deprecated tag:v6.5.0 - The keys of IdSearchResult should always be field's propertyName instead of storageName
                $data[$field->getPropertyName()] = $value;
                if (!Feature::isActive('v6.5.0.0')) {
                    $data[$storageName] = $value;
                }

                if (!$field->is(PrimaryKey::class)) {
                    continue;
                }

                // @deprecated tag:v6.5.0 - The keys of IdSearchResult should always be field's propertyName instead of storageName
                $pk[$field->getPropertyName()] = $value;
                if (!Feature::isActive('v6.5.0.0')) {
                    $pk[$storageName] = $value;
                }
            }

            /**
             * @deprecated tag:v6.5.0 - Will be change to $arrayKey = implode('-', $pk) due to no duplicated ids as mentioned above;
             */
            $arrayKey = implode('-', array_unique(array_values($pk)));

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

        $query->resetQueryPart('orderBy');
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
