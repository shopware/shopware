<?php declare(strict_types=1);

namespace Shopware\Core\System\Tag\Service;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\CriteriaQueryBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\QueryBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\Tag\Struct\FilteredTagIdsStruct;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
class FilterTagIdsService
{
    private EntityDefinition $tagDefinition;

    private Connection $connection;

    private CriteriaQueryBuilder $criteriaQueryBuilder;

    public function __construct(
        EntityDefinition $tagDefinition,
        Connection $connection,
        CriteriaQueryBuilder $criteriaQueryBuilder
    ) {
        $this->tagDefinition = $tagDefinition;
        $this->connection = $connection;
        $this->criteriaQueryBuilder = $criteriaQueryBuilder;
    }

    public function filterIds(Request $request, Criteria $criteria, Context $context): FilteredTagIdsStruct
    {
        $query = $this->getIdsQuery($criteria, $context);
        $duplicateFilter = $request->get('duplicateFilter', false);
        $emptyFilter = $request->get('emptyFilter', false);
        $assignmentFilter = $request->get('assignmentFilter', false);

        if ($emptyFilter) {
            $this->addEmptyFilter($query);
        }

        if ($duplicateFilter) {
            $this->addDuplicateFilter($query);
        }

        if (\is_array($assignmentFilter)) {
            $this->addAssignmentFilter($query, $assignmentFilter);
        }

        $ids = $query->execute()->fetchFirstColumn();

        return new FilteredTagIdsStruct($ids, $this->getTotal($query));
    }

    private function getIdsQuery(Criteria $criteria, Context $context): QueryBuilder
    {
        $query = new QueryBuilder($this->connection);

        $query = $this->criteriaQueryBuilder->build($query, $this->tagDefinition, $criteria, $context);

        /** @var string[] $select */
        $select = array_merge(['LOWER(HEX(`tag`.`id`))'], $query->getQueryPart('select'));
        $query->select($select);
        $query->addGroupBy('`tag`.`id`');
        $query->setMaxResults($criteria->getLimit());
        $query->setFirstResult($criteria->getOffset() ?? 0);

        return $query;
    }

    private function getTotal(QueryBuilder $query): int
    {
        $query->setMaxResults(null);
        $query->setFirstResult(0);

        $total = (new QueryBuilder($query->getConnection()))
            ->select(['COUNT(*)'])
            ->from(sprintf('(%s) total', $query->getSQL()))
            ->setParameters($query->getParameters(), $query->getParameterTypes());

        return (int) $total->execute()->fetchOne();
    }

    private function addEmptyFilter(QueryBuilder $query): void
    {
        /** @var ManyToManyAssociationField[] $manyToManyFields */
        $manyToManyFields = $this->tagDefinition->getFields()->filter(function (Field $field) {
            return $field instanceof ManyToManyAssociationField;
        });

        foreach ($manyToManyFields as $manyToManyField) {
            $mappingTable = EntityDefinitionQueryHelper::escape($manyToManyField->getMappingDefinition()->getEntityName());
            $mappingLocalColumn = EntityDefinitionQueryHelper::escape($manyToManyField->getMappingLocalColumn());

            $subQuery = (new QueryBuilder($this->connection))
                ->select([$mappingLocalColumn])
                ->from($mappingTable);

            $query->andWhere($query->expr()->notIn('`tag`.`id`', sprintf('(%s)', $subQuery->getSQL())));
        }
    }

    private function addDuplicateFilter(QueryBuilder $query): void
    {
        $subQuery = (new QueryBuilder($this->connection))
            ->select(['name'])
            ->from('tag')
            ->groupBy('name')
            ->having('COUNT(`name`) > 1');

        $query->innerJoin(
            '`tag`',
            sprintf('(%s)', $subQuery->getSQL()),
            'duplicate',
            'duplicate.`name` = `tag`.`name`'
        );
    }

    private function addAssignmentFilter(QueryBuilder $query, array $assignments): void
    {
        /** @var ManyToManyAssociationField[] $manyToManyFields */
        $manyToManyFields = $this->tagDefinition->getFields()->filter(function (Field $field) use ($assignments) {
            return $field instanceof ManyToManyAssociationField && \in_array($field->getPropertyName(), $assignments, true);
        });

        if (\count($manyToManyFields) === 0) {
            return;
        }

        $expressions = $query->expr()->orX();

        foreach ($manyToManyFields as $manyToManyField) {
            $mappingTable = EntityDefinitionQueryHelper::escape($manyToManyField->getMappingDefinition()->getEntityName());
            $mappingLocalColumn = EntityDefinitionQueryHelper::escape($manyToManyField->getMappingLocalColumn());

            $subQuery = (new QueryBuilder($this->connection))
                ->select([$mappingLocalColumn])
                ->from($mappingTable);

            $expressions->add($query->expr()->in('`tag`.`id`', sprintf('(%s)', $subQuery->getSQL())));
        }

        $query->andWhere($expressions);
    }
}
