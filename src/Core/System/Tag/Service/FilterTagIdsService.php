<?php declare(strict_types=1);

namespace Shopware\Core\System\Tag\Service;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\Expression\CompositeExpression;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\CriteriaQueryBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\QueryBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Tag\Struct\FilteredTagIdsStruct;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('business-ops')]
class FilterTagIdsService
{
    public function __construct(
        private readonly EntityDefinition $tagDefinition,
        private readonly Connection $connection,
        private readonly CriteriaQueryBuilder $criteriaQueryBuilder
    ) {
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

        $ids = $query->executeQuery()->fetchFirstColumn();

        return new FilteredTagIdsStruct($ids, $this->getTotal($query));
    }

    private function getIdsQuery(Criteria $criteria, Context $context): QueryBuilder
    {
        $query = new QueryBuilder($this->connection);

        $query = $this->criteriaQueryBuilder->build($query, $this->tagDefinition, $criteria, $context);

        /** @var array<string> $select */
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

        $total = (new QueryBuilder($this->connection))
            ->select(['COUNT(*)'])
            ->from(sprintf('(%s) total', $query->getSQL()))
            ->setParameters($query->getParameters(), $query->getParameterTypes());

        return (int) $total->executeQuery()->fetchOne();
    }

    private function addEmptyFilter(QueryBuilder $query): void
    {
        /** @var ManyToManyAssociationField[] $manyToManyFields */
        $manyToManyFields = $this->tagDefinition->getFields()->filter(fn (Field $field) => $field instanceof ManyToManyAssociationField);

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
        $manyToManyFields = $this->tagDefinition->getFields()->filter(fn (Field $field) => $field instanceof ManyToManyAssociationField && \in_array($field->getPropertyName(), $assignments, true));

        if (\count($manyToManyFields) === 0) {
            return;
        }

        $expressions = new CompositeExpression(CompositeExpression::TYPE_OR);

        foreach ($manyToManyFields as $manyToManyField) {
            $mappingTable = EntityDefinitionQueryHelper::escape($manyToManyField->getMappingDefinition()->getEntityName());
            $mappingLocalColumn = EntityDefinitionQueryHelper::escape($manyToManyField->getMappingLocalColumn());

            $subQuery = (new QueryBuilder($this->connection))
                ->select([$mappingLocalColumn])
                ->from($mappingTable);

            $expressions = $expressions->with($query->expr()->in('`tag`.`id`', sprintf('(%s)', $subQuery->getSQL())));
        }

        $query->andWhere($expressions);
    }
}
