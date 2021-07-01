<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Dbal;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Exception\ParentAssociationCanNotBeFetched;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\FetchModeHelper;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\AssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ChildrenAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Extension;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Inherited;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Runtime;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ParentAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StorageAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Read\EntityReaderInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Parser\SqlQueryParser;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Struct\ArrayEntity;
use Shopware\Core\Framework\Uuid\Uuid;

class EntityReader implements EntityReaderInterface
{
    public const INTERNAL_MAPPING_STORAGE = 'internal_mapping_storage';
    public const FOREIGN_KEYS = 'foreignKeys';
    public const MANY_TO_MANY_LIMIT_QUERY = 'many_to_many_limit_query';

    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var EntityHydrator
     */
    private $hydrator;

    /**
     * @var EntityDefinitionQueryHelper
     */
    private $queryHelper;

    /**
     * @var SqlQueryParser
     */
    private $parser;

    /**
     * @var CriteriaQueryBuilder
     */
    private $criteriaQueryBuilder;

    public function __construct(
        Connection $connection,
        EntityHydrator $hydrator,
        EntityDefinitionQueryHelper $queryHelper,
        SqlQueryParser $parser,
        CriteriaQueryBuilder $criteriaQueryBuilder
    ) {
        $this->connection = $connection;
        $this->hydrator = $hydrator;
        $this->queryHelper = $queryHelper;
        $this->parser = $parser;
        $this->criteriaQueryBuilder = $criteriaQueryBuilder;
    }

    public function read(EntityDefinition $definition, Criteria $criteria, Context $context): EntityCollection
    {
        $criteria->resetSorting();
        $criteria->resetQueries();

        $collectionClass = $definition->getCollectionClass();

        return $this->_read(
            $criteria,
            $definition,
            $context,
            EntityHydrator::createClass($collectionClass),
            $definition->getFields()->getBasicFields()
        );
    }

    protected function getParser(): SqlQueryParser
    {
        return $this->parser;
    }

    private function _read(
        Criteria $criteria,
        EntityDefinition $definition,
        Context $context,
        EntityCollection $collection,
        FieldCollection $fields
    ): EntityCollection {
        $hasFilters = !empty($criteria->getFilters()) || !empty($criteria->getPostFilters());
        $hasIds = !empty($criteria->getIds());

        if (!$hasFilters && !$hasIds) {
            return $collection;
        }

        $fields = $this->addAssociationFieldsToCriteria($criteria, $definition, $fields);

        if ($definition->isInheritanceAware() && $criteria->hasAssociation('parent')) {
            throw new ParentAssociationCanNotBeFetched();
        }

        $rows = $this->fetch($criteria, $definition, $context, $fields);

        $collection = $this->hydrator->hydrate($collection, $definition->getEntityClass(), $definition, $rows, $definition->getEntityName(), $context);

        $collection = $this->fetchAssociations($criteria, $definition, $context, $collection, $fields);

        if ($hasIds && empty($criteria->getSorting())) {
            $collection->sortByIdArray($criteria->getIds());
        }

        return $collection;
    }

    private function joinBasic(
        EntityDefinition $definition,
        Context $context,
        string $root,
        QueryBuilder $query,
        FieldCollection $fields,
        ?Criteria $criteria = null
    ): void {
        $filtered = $fields->fmap(function (Field $field) {
            if ($field->is(Runtime::class)) {
                return null;
            }

            return $field;
        });

        $parentAssociation = null;

        if ($definition->isInheritanceAware() && $context->considerInheritance()) {
            $parentAssociation = $definition->getFields()->get('parent');
            $this->queryHelper->resolveField($parentAssociation, $definition, $root, $query, $context);
        }

        /** @var Field $field */
        foreach ($filtered as $field) {
            //translated fields are handled after loop all together
            if ($field instanceof TranslatedField) {
                $this->queryHelper->resolveField($field, $definition, $root, $query, $context);

                continue;
            }

            //self references can not be resolved if set to autoload, otherwise we get an endless loop
            if (!$field instanceof ParentAssociationField && $field instanceof AssociationField && $field->getAutoload() && $field->getReferenceDefinition() === $definition) {
                continue;
            }

            //many to one associations can be directly fetched in same query
            if ($field instanceof ManyToOneAssociationField || $field instanceof OneToOneAssociationField) {
                $reference = $field->getReferenceDefinition();

                $basics = $reference->getFields()->getBasicFields();

                $this->queryHelper->resolveField($field, $definition, $root, $query, $context);

                $alias = $root . '.' . $field->getPropertyName();

                $joinCriteria = null;
                if ($criteria && $criteria->hasAssociation($field->getPropertyName())) {
                    $joinCriteria = $criteria->getAssociation($field->getPropertyName());
                    $basics = $this->addAssociationFieldsToCriteria($joinCriteria, $reference, $basics);
                }

                $this->joinBasic($reference, $context, $alias, $query, $basics, $joinCriteria);

                continue;
            }

            //add sub select for many to many field
            if ($field instanceof ManyToManyAssociationField) {
                if ($this->isAssociationRestricted($criteria, $field->getPropertyName())) {
                    continue;
                }

                //requested a paginated, filtered or sorted list

                $this->addManyToManySelect($definition, $root, $field, $query, $context);

                continue;
            }

            //other associations like OneToManyAssociationField fetched lazy by additional query
            if ($field instanceof AssociationField) {
                continue;
            }

            if ($parentAssociation !== null
                && $field instanceof StorageAware
                && $field->is(Inherited::class)
                && $context->considerInheritance()
            ) {
                $parentAlias = $root . '.' . $parentAssociation->getPropertyName();

                //contains the field accessor for the child value (eg. `product.name`.`name`)
                $childAccessor = EntityDefinitionQueryHelper::escape($root) . '.'
                    . EntityDefinitionQueryHelper::escape($field->getStorageName());

                //contains the field accessor for the parent value (eg. `product.parent`.`name`)
                $parentAccessor = EntityDefinitionQueryHelper::escape($parentAlias) . '.'
                    . EntityDefinitionQueryHelper::escape($field->getStorageName());

                //contains the alias for the resolved field (eg. `product.name`)
                $fieldAlias = EntityDefinitionQueryHelper::escape($root . '.' . $field->getPropertyName());

                if ($field instanceof JsonField) {
                    // merged in hydrator
                    $parentFieldAlias = EntityDefinitionQueryHelper::escape($root . '.' . $field->getPropertyName() . '.inherited');
                    $query->addSelect(sprintf('%s as %s', $parentAccessor, $parentFieldAlias));
                }
                //add selection for resolved parent-child inheritance field
                $query->addSelect(sprintf('COALESCE(%s, %s) as %s', $childAccessor, $parentAccessor, $fieldAlias));

                continue;
            }

            //all other StorageAware fields are stored inside the main entity
            if ($field instanceof StorageAware) {
                $query->addSelect(
                    EntityDefinitionQueryHelper::escape($root) . '.'
                    . EntityDefinitionQueryHelper::escape($field->getStorageName()) . ' as '
                    . EntityDefinitionQueryHelper::escape($root . '.' . $field->getPropertyName())
                );
            }
        }

        $this->queryHelper->addTranslationSelect($root, $definition, $query, $context);
    }

    private function fetch(Criteria $criteria, EntityDefinition $definition, Context $context, FieldCollection $fields): array
    {
        $table = $definition->getEntityName();

        $query = $this->criteriaQueryBuilder->build(
            new QueryBuilder($this->connection),
            $definition,
            $criteria,
            $context
        );

        $this->joinBasic($definition, $context, $table, $query, $fields, $criteria);

        if (!empty($criteria->getIds())) {
            $this->queryHelper->addIdCondition($criteria, $definition, $query);
        }

        if ($criteria->getTitle()) {
            $query->setTitle($criteria->getTitle() . '::read');
        }

        return $query->execute()->fetchAll();
    }

    private function loadManyToMany(
        Criteria $criteria,
        ManyToManyAssociationField $association,
        Context $context,
        EntityCollection $collection
    ): void {
        $associationCriteria = $criteria->getAssociation($association->getPropertyName());

        if (!$associationCriteria->getTitle() && $criteria->getTitle()) {
            $associationCriteria->setTitle(
                $criteria->getTitle() . '::association::' . $association->getPropertyName()
            );
        }

        //check if the requested criteria is restricted (limit, offset, sorting, filtering)
        if ($this->isAssociationRestricted($criteria, $association->getPropertyName())) {
            //if restricted load paginated list of many to many
            $this->loadManyToManyWithCriteria($associationCriteria, $association, $context, $collection);

            return;
        }

        //otherwise the association is loaded in the root query of the entity as sub select which contains all ids
        //the ids are extracted in the entity hydrator (see: \Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityHydrator::extractManyToManyIds)
        $this->loadManyToManyOverExtension($associationCriteria, $association, $context, $collection);
    }

    private function addManyToManySelect(
        EntityDefinition $definition,
        string $root,
        ManyToManyAssociationField $field,
        QueryBuilder $query,
        Context $context
    ): void {
        $mapping = $field->getMappingDefinition();

        $versionCondition = '';
        if ($mapping->isVersionAware() && $definition->isVersionAware() && $field->is(CascadeDelete::class)) {
            $versionField = $definition->getEntityName() . '_version_id';
            $versionCondition = ' AND #alias#.' . $versionField . ' = #root#.version_id';
        }

        $source = EntityDefinitionQueryHelper::escape($root) . '.' . EntityDefinitionQueryHelper::escape($field->getLocalField());
        if ($field->is(Inherited::class) && $context->considerInheritance()) {
            $source = EntityDefinitionQueryHelper::escape($root) . '.' . EntityDefinitionQueryHelper::escape($field->getPropertyName());
        }

        $parameters = [
            '#alias#' => EntityDefinitionQueryHelper::escape($root . '.' . $field->getPropertyName() . '.mapping'),
            '#mapping_reference_column#' => EntityDefinitionQueryHelper::escape($field->getMappingReferenceColumn()),
            '#mapping_table#' => EntityDefinitionQueryHelper::escape($mapping->getEntityName()),
            '#mapping_local_column#' => EntityDefinitionQueryHelper::escape($field->getMappingLocalColumn()),
            '#root#' => EntityDefinitionQueryHelper::escape($root),
            '#source#' => $source,
            '#property#' => EntityDefinitionQueryHelper::escape($root . '.' . $field->getPropertyName() . '.id_mapping'),
        ];

        $query->addSelect(
            str_replace(
                array_keys($parameters),
                array_values($parameters),
                '(SELECT GROUP_CONCAT(HEX(#alias#.#mapping_reference_column#) SEPARATOR \'||\')
                  FROM #mapping_table# #alias#
                  WHERE #alias#.#mapping_local_column# = #source#'
                  . $versionCondition
                  . ' ) as #property#'
            )
        );
    }

    private function collectManyToManyIds(EntityCollection $collection, AssociationField $association): array
    {
        $ids = [];
        $property = $association->getPropertyName();
        foreach ($collection as $struct) {
            /** @var string[] $tmp */
            $tmp = $struct->getExtension(self::INTERNAL_MAPPING_STORAGE)->get($property);
            foreach ($tmp as $id) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    private function loadOneToMany(
        Criteria $criteria,
        EntityDefinition $definition,
        OneToManyAssociationField $association,
        Context $context,
        EntityCollection $collection
    ): void {
        $fieldCriteria = new Criteria();
        if ($criteria->hasAssociation($association->getPropertyName())) {
            $fieldCriteria = $criteria->getAssociation($association->getPropertyName());
        }

        if (!$fieldCriteria->getTitle() && $criteria->getTitle()) {
            $fieldCriteria->setTitle(
                $criteria->getTitle() . '::association::' . $association->getPropertyName()
            );
        }

        //association should not be paginated > load data over foreign key condition
        if ($fieldCriteria->getLimit() === null) {
            $this->loadOneToManyWithoutPagination($definition, $association, $context, $collection, $fieldCriteria);

            return;
        }

        //load association paginated > use internal counter loops
        $this->loadOneToManyWithPagination($definition, $association, $context, $collection, $fieldCriteria);
    }

    private function loadOneToManyWithoutPagination(
        EntityDefinition $definition,
        OneToManyAssociationField $association,
        Context $context,
        EntityCollection $collection,
        Criteria $fieldCriteria
    ): void {
        $ref = $association->getReferenceDefinition()->getFields()->getByStorageName(
            $association->getReferenceField()
        );

        $propertyName = $ref->getPropertyName();
        if ($association instanceof ChildrenAssociationField) {
            $propertyName = 'parentId';
        }

        //build orm property accessor to add field sortings and conditions `customer_address.customerId`
        $propertyAccessor = $association->getReferenceDefinition()->getEntityName() . '.' . $propertyName;

        $ids = array_values($collection->getIds());

        $isInheritanceAware = $definition->isInheritanceAware();

        if ($isInheritanceAware) {
            $parentIds = $collection->map(function (Entity $entity) {
                return $entity->get('parentId');
            });

            $parentIds = array_values(array_filter($parentIds));

            $ids = array_unique(array_merge($ids, $parentIds));
        }

        $fieldCriteria->addFilter(new EqualsAnyFilter($propertyAccessor, $ids));

        $referenceClass = $association->getReferenceDefinition();
        $collectionClass = $referenceClass->getCollectionClass();

        $data = $this->_read(
            $fieldCriteria,
            $referenceClass,
            $context,
            EntityHydrator::createClass($collectionClass),
            $referenceClass->getFields()->getBasicFields()
        );

        $grouped = [];
        foreach ($data as $entity) {
            $fk = $entity->get($propertyName);

            $grouped[$fk][] = $entity;
        }

        //assign loaded data to root entities
        foreach ($collection as $entity) {
            $structData = EntityHydrator::createClass($collectionClass);
            if (isset($grouped[$entity->getUniqueIdentifier()])) {
                $structData->fill($grouped[$entity->getUniqueIdentifier()]);
            }

            //assign data of child immediately
            if ($association->is(Extension::class)) {
                $entity->addExtension($association->getPropertyName(), $structData);
            } else {
                //otherwise the data will be assigned directly as properties
                $entity->assign([$association->getPropertyName() => $structData]);
            }

            if (!$association->is(Inherited::class) || $structData->count() > 0 || !$context->considerInheritance()) {
                continue;
            }

            //if association can be inherited by the parent and the struct data is empty, filter again for the parent id
            $structData = EntityHydrator::createClass($collectionClass);
            if (isset($grouped[$entity->get('parentId')])) {
                $structData->fill($grouped[$entity->get('parentId')]);
            }

            if ($association->is(Extension::class)) {
                $entity->addExtension($association->getPropertyName(), $structData);

                continue;
            }
            $entity->assign([$association->getPropertyName() => $structData]);
        }
    }

    private function loadOneToManyWithPagination(
        EntityDefinition $definition,
        OneToManyAssociationField $association,
        Context $context,
        EntityCollection $collection,
        Criteria $fieldCriteria
    ): void {
        $propertyAccessor = $this->buildOneToManyPropertyAccessor($definition, $association);

        //inject sorting for foreign key, otherwise the internal counter wouldn't work `order by customer_address.customer_id, other_sortings`
        $sorting = array_merge(
            [new FieldSorting($propertyAccessor, FieldSorting::ASCENDING)],
            $fieldCriteria->getSorting()
        );

        $fieldCriteria->resetSorting();
        $fieldCriteria->addSorting(...$sorting);

        //add terms query to filter reference table to loaded root entities: `customer_address.customerId IN (:loadedIds)`
        $fieldCriteria->addFilter(new EqualsAnyFilter($propertyAccessor, array_values($collection->getIds())));

        $mapping = $this->fetchPaginatedOneToManyMapping($definition, $association, $context, $collection, $fieldCriteria);

        $ids = [];
        foreach ($mapping as $associationIds) {
            foreach ($associationIds as $associationId) {
                $ids[] = $associationId;
            }
        }

        $fieldCriteria->setIds(array_filter($ids));
        $fieldCriteria->resetSorting();
        $fieldCriteria->resetFilters();
        $fieldCriteria->resetPostFilters();

        $referenceClass = $association->getReferenceDefinition();
        $collectionClass = $referenceClass->getCollectionClass();
        $data = $this->_read(
            $fieldCriteria,
            $referenceClass,
            $context,
            EntityHydrator::createClass($collectionClass),
            $referenceClass->getFields()->getBasicFields()
        );

        //assign loaded reference collections to root entities
        /** @var Entity $entity */
        foreach ($collection as $entity) {
            //extract mapping ids for the current entity
            $mappingIds = $mapping[$entity->getUniqueIdentifier()];

            $structData = $data->getList($mappingIds);

            //assign data of child immediately
            if ($association->is(Extension::class)) {
                $entity->addExtension($association->getPropertyName(), $structData);
            } else {
                $entity->assign([$association->getPropertyName() => $structData]);
            }

            if (!$association->is(Inherited::class) || $structData->count() > 0 || !$context->considerInheritance()) {
                continue;
            }

            $parentId = $entity->get('parentId');

            //extract mapping ids for the current entity
            $mappingIds = $mapping[$parentId];

            $structData = $data->getList($mappingIds);

            //assign data of child immediately
            if ($association->is(Extension::class)) {
                $entity->addExtension($association->getPropertyName(), $structData);
            } else {
                $entity->assign([$association->getPropertyName() => $structData]);
            }
        }
    }

    private function loadManyToManyOverExtension(
        Criteria $criteria,
        ManyToManyAssociationField $association,
        Context $context,
        EntityCollection $collection
    ): void {
        //collect all ids of many to many association which already stored inside the struct instances
        $ids = $this->collectManyToManyIds($collection, $association);
        $criteria->setIds($ids);

        $referenceClass = $association->getToManyReferenceDefinition();
        $collectionClass = $referenceClass->getCollectionClass();

        $data = $this->_read(
            $criteria,
            $referenceClass,
            $context,
            EntityHydrator::createClass($collectionClass),
            $referenceClass->getFields()->getBasicFields()
        );

        /** @var Entity $struct */
        foreach ($collection as $struct) {
            /** @var ArrayEntity $extension */
            $extension = $struct->getExtension(self::INTERNAL_MAPPING_STORAGE);

            //use assign function to avoid setter name building
            $structData = $data->getList(
                $extension->get($association->getPropertyName())
            );

            //if the association is added as extension (for plugins), we have to add the data as extension
            if ($association->is(Extension::class)) {
                $struct->addExtension($association->getPropertyName(), $structData);
            } else {
                $struct->assign([$association->getPropertyName() => $structData]);
            }
        }
    }

    private function loadManyToManyWithCriteria(
        Criteria $fieldCriteria,
        ManyToManyAssociationField $association,
        Context $context,
        EntityCollection $collection
    ): void {
        $fields = $association->getToManyReferenceDefinition()->getFields();
        $reference = null;
        foreach ($fields as $field) {
            if (!$field instanceof ManyToManyAssociationField) {
                continue;
            }

            if ($field->getReferenceDefinition() !== $association->getReferenceDefinition()) {
                continue;
            }

            $reference = $field;

            break;
        }

        if (!$reference) {
            throw new \RuntimeException(
                sprintf(
                    'No inverse many to many association found, for association %s',
                    $association->getPropertyName()
                )
            );
        }

        //build inverse accessor `product.categories.id`
        $accessor = $association->getToManyReferenceDefinition()->getEntityName() . '.' . $reference->getPropertyName() . '.id';

        $fieldCriteria->addFilter(new EqualsAnyFilter($accessor, $collection->getIds()));

        $root = EntityDefinitionQueryHelper::escape(
            $association->getToManyReferenceDefinition()->getEntityName() . '.' . $reference->getPropertyName() . '.mapping'
        );

        $query = new QueryBuilder($this->connection);
        // to many selects results in a `group by` clause. In this case the order by parts will be executed with MIN/MAX aggregation
        // but at this point the order by will be moved to an sub select where we don't have a group state, the `state` prevents this behavior
        $query->addState(self::MANY_TO_MANY_LIMIT_QUERY);

        $query = $this->criteriaQueryBuilder->build(
            $query,
            $association->getToManyReferenceDefinition(),
            $fieldCriteria,
            $context
        );

        $localColumn = EntityDefinitionQueryHelper::escape($association->getMappingLocalColumn());
        $referenceColumn = EntityDefinitionQueryHelper::escape($association->getMappingReferenceColumn());

        $orderBy = '';
        $parts = $query->getQueryPart('orderBy');
        if (!empty($parts)) {
            $orderBy = ' ORDER BY ' . implode(', ', $parts);
            $query->resetQueryPart('orderBy');
        }
        // order by is handled in group_concat
        $fieldCriteria->resetSorting();

        $query->select([
            'LOWER(HEX(' . $root . '.' . $localColumn . ')) as `key`',
            'GROUP_CONCAT(LOWER(HEX(' . $root . '.' . $referenceColumn . ')) ' . $orderBy . ') as `value`',
        ]);

        $query->addGroupBy($root . '.' . $localColumn);

        if ($fieldCriteria->getLimit() !== null) {
            $limitQuery = $this->buildManyToManyLimitQuery($association);

            $params = [
                '#source_column#' => EntityDefinitionQueryHelper::escape($association->getMappingLocalColumn()),
                '#reference_column#' => EntityDefinitionQueryHelper::escape($association->getMappingReferenceColumn()),
                '#table#' => $root,
            ];
            $query->innerJoin(
                $root,
                '(' . $limitQuery . ')',
                'counter_table',
                str_replace(
                    array_keys($params),
                    array_values($params),
                    'counter_table.#source_column# = #table#.#source_column# AND
                     counter_table.#reference_column# = #table#.#reference_column# AND
                     counter_table.id_count <= :limit'
                )
            );
            $query->setParameter('limit', $fieldCriteria->getLimit());

            $this->connection->executeQuery('SET @n = 0; SET @c = null;');
        }

        $mapping = $query->execute()->fetchAll();
        $mapping = FetchModeHelper::keyPair($mapping);

        $ids = [];
        foreach ($mapping as &$row) {
            $row = array_filter(explode(',', $row));
            foreach ($row as $id) {
                $ids[] = $id;
            }
        }
        unset($row);

        $fieldCriteria->setIds($ids);

        $referenceClass = $association->getToManyReferenceDefinition();
        $collectionClass = $referenceClass->getCollectionClass();
        $data = $this->_read(
            $fieldCriteria,
            $referenceClass,
            $context,
            EntityHydrator::createClass($collectionClass),
            $referenceClass->getFields()->getBasicFields()
        );

        /** @var Entity $struct */
        foreach ($collection as $struct) {
            $structData = EntityHydrator::createClass($collectionClass);

            $id = $struct->getUniqueIdentifier();

            $parentId = $struct->has('parentId') ? $struct->get('parentId') : '';

            if (\array_key_exists($struct->getUniqueIdentifier(), $mapping)) {
                //filter mapping list of whole data array
                $structData = $data->getList($mapping[$id]);

                //sort list by ids if the criteria contained a sorting
                $structData->sortByIdArray($mapping[$id]);
            } elseif (\array_key_exists($parentId, $mapping) && $association->is(Inherited::class) && $context->considerInheritance()) {
                //filter mapping for the inherited parent association
                $structData = $data->getList($mapping[$parentId]);

                //sort list by ids if the criteria contained a sorting
                $structData->sortByIdArray($mapping[$parentId]);
            }

            //if the association is added as extension (for plugins), we have to add the data as extension
            if ($association->is(Extension::class)) {
                $struct->addExtension($association->getPropertyName(), $structData);
            } else {
                $struct->assign([$association->getPropertyName() => $structData]);
            }
        }
    }

    private function fetchPaginatedOneToManyMapping(
        EntityDefinition $definition,
        OneToManyAssociationField $association,
        Context $context,
        EntityCollection $collection,
        Criteria $fieldCriteria
    ): array {
        //build query based on provided association criteria (sortings, search, filter)
        $query = $this->criteriaQueryBuilder->build(
            new QueryBuilder($this->connection),
            $association->getReferenceDefinition(),
            $fieldCriteria,
            $context
        );

        $foreignKey = $association->getReferenceField();

        //build sql accessor for foreign key field in reference table `customer_address.customer_id`
        $sqlAccessor = EntityDefinitionQueryHelper::escape($association->getReferenceDefinition()->getEntityName()) . '.'
            . EntityDefinitionQueryHelper::escape($foreignKey);

        $query->select(
            [
                //build select with an internal counter loop, the counter loop will be reset if the foreign key changed (this is the reason for the sorting inject above)
                '@n:=IF(@c=' . $sqlAccessor . ', @n+1, IF(@c:=' . $sqlAccessor . ',1,1)) as id_count',

                //add select for foreign key for join condition
                $sqlAccessor,

                //add primary key select to group concat them
                EntityDefinitionQueryHelper::escape($association->getReferenceDefinition()->getEntityName()) . '.id',
            ]
        );

        $root = EntityDefinitionQueryHelper::escape($definition->getEntityName());

        //create a wrapper query which select the root primary key and the grouped reference ids
        $wrapper = $this->connection->createQueryBuilder();
        $wrapper->select(
            [
                'LOWER(HEX(' . $root . '.id)) as id',
                'LOWER(HEX(child.id)) as child_id',
            ]
        );

        $wrapper->from($root, $root);

        //wrap query into a sub select to restrict the association count from the outer query
        $wrapper->leftJoin(
            $root,
            '(' . $query->getSQL() . ')',
            'child',
            'child.' . $foreignKey . ' = ' . $root . '.id AND id_count >= :offset AND id_count <= :limit'
        );

        //filter result to loaded root entities
        $wrapper->andWhere($root . '.id IN (:rootIds)');

        $bytes = $collection->map(
            function (Entity $entity) {
                return Uuid::fromHexToBytes($entity->getUniqueIdentifier());
            }
        );

        $wrapper->setParameter('rootIds', $bytes, Connection::PARAM_STR_ARRAY);

        $limit = $fieldCriteria->getOffset() + $fieldCriteria->getLimit();
        $offset = $fieldCriteria->getOffset() + 1;

        $wrapper->setParameter('limit', $limit);
        $wrapper->setParameter('offset', $offset);

        foreach ($query->getParameters() as $key => $value) {
            $type = $query->getParameterType($key);
            $wrapper->setParameter($key, $value, $type);
        }

        //initials the cursor and loop counter, pdo do not allow to execute SET and SELECT in one statement
        $this->connection->executeQuery('SET @n = 0; SET @c = null;');

        $rows = $wrapper->execute()->fetchAll();

        $grouped = [];
        foreach ($rows as $row) {
            $id = $row['id'];

            if (!isset($grouped[$id])) {
                $grouped[$id] = [];
            }

            if (empty($row['child_id'])) {
                continue;
            }

            $grouped[$id][] = $row['child_id'];
        }

        return $grouped;
    }

    private function buildManyToManyLimitQuery(ManyToManyAssociationField $association): QueryBuilder
    {
        $table = EntityDefinitionQueryHelper::escape($association->getMappingDefinition()->getEntityName());

        $sourceColumn = EntityDefinitionQueryHelper::escape($association->getMappingLocalColumn());
        $referenceColumn = EntityDefinitionQueryHelper::escape($association->getMappingReferenceColumn());

        $params = [
            '#table#' => $table,
            '#source_column#' => $sourceColumn,
        ];

        $query = new QueryBuilder($this->connection);
        $query->select([
            str_replace(
                array_keys($params),
                array_values($params),
                '@n:=IF(@c=#table#.#source_column#, @n+1, IF(@c:=#table#.#source_column#,1,1)) as id_count'
            ),
            $table . '.' . $referenceColumn,
            $table . '.' . $sourceColumn,
        ]);
        $query->from($table, $table);
        $query->orderBy($table . '.' . $sourceColumn);

        return $query;
    }

    private function buildOneToManyPropertyAccessor(EntityDefinition $definition, OneToManyAssociationField $association): string
    {
        $reference = $association->getReferenceDefinition();

        if ($association instanceof ChildrenAssociationField) {
            return $reference->getEntityName() . '.parentId';
        }

        $ref = $reference->getFields()->getByStorageName(
            $association->getReferenceField()
        );

        if (!$ref) {
            throw new \RuntimeException(
                sprintf(
                    'Reference field %s not found in definition %s for definition %s',
                    $association->getReferenceField(),
                    $reference->getEntityName(),
                    $definition->getEntityName()
                )
            );
        }

        return $reference->getEntityName() . '.' . $ref->getPropertyName();
    }

    private function isAssociationRestricted(?Criteria $criteria, string $accessor): bool
    {
        if ($criteria === null) {
            return false;
        }

        if (!$criteria->hasAssociation($accessor)) {
            return false;
        }

        $fieldCriteria = $criteria->getAssociation($accessor);

        return $fieldCriteria->getOffset() !== null
            || $fieldCriteria->getLimit() !== null
            || !empty($fieldCriteria->getSorting())
            || !empty($fieldCriteria->getFilters())
            || !empty($fieldCriteria->getPostFilters())
        ;
    }

    private function addAssociationFieldsToCriteria(
        Criteria $criteria,
        EntityDefinition $definition,
        FieldCollection $fields
    ): FieldCollection {
        foreach ($criteria->getAssociations() as $fieldName => $_fieldCriteria) {
            $regex = sprintf('#^(%s\.)?(extensions\.)?#i', $definition->getEntityName());
            $fieldName = preg_replace($regex, '', $fieldName);

            $dotPosition = mb_strpos($fieldName, '.');
            if ($dotPosition !== false) {
                $fieldName = mb_substr($fieldName, 0, $dotPosition);
            }

            $field = $definition->getFields()->get($fieldName);
            if (!$field) {
                continue;
            }

            $fields->add($field);
        }

        return $fields;
    }

    private function loadToOne(
        AssociationField $association,
        Context $context,
        EntityCollection $collection,
        Criteria $criteria
    ): void {
        if (!$association instanceof OneToOneAssociationField && !$association instanceof ManyToOneAssociationField) {
            return;
        }

        if (!$criteria->hasAssociation($association->getPropertyName())) {
            return;
        }

        $associationCriteria = $criteria->getAssociation($association->getPropertyName());
        if (!$associationCriteria->getAssociations()) {
            return;
        }

        if (!$associationCriteria->getTitle() && $criteria->getTitle()) {
            $associationCriteria->setTitle(
                $criteria->getTitle() . '::association::' . $association->getPropertyName()
            );
        }

        $related = $collection->map(function (Entity $entity) use ($association) {
            if ($association->is(Extension::class)) {
                return $entity->getExtension($association->getPropertyName());
            }

            return $entity->get($association->getPropertyName());
        });

        $related = array_filter($related);

        $referenceDefinition = $association->getReferenceDefinition();
        $collectionClass = $referenceDefinition->getCollectionClass();

        $fields = $referenceDefinition->getFields()->getBasicFields();
        $fields = $this->addAssociationFieldsToCriteria($associationCriteria, $referenceDefinition, $fields);

        // This line removes duplicate entries, so after fetchAssociations the association must be reassigned
        /** @var EntityCollection $relatedCollection */
        $relatedCollection = EntityHydrator::createClass($collectionClass);
        $relatedCollection->fill($related);

        $this->fetchAssociations($associationCriteria, $referenceDefinition, $context, $relatedCollection, $fields);

        /** @var Entity $entity */
        foreach ($collection as $entity) {
            if ($association->is(Extension::class)) {
                $item = $entity->getExtension($association->getPropertyName());
            } else {
                $item = $entity->get($association->getPropertyName());
            }

            /** @var Entity|null $item */
            if ($item === null) {
                continue;
            }

            if ($association->is(Extension::class)) {
                $entity->addExtension($association->getPropertyName(), $relatedCollection->get($item->getUniqueIdentifier()));

                continue;
            }

            $entity->assign([
                $association->getPropertyName() => $relatedCollection->get($item->getUniqueIdentifier()),
            ]);
        }
    }

    private function fetchAssociations(
        Criteria $criteria,
        EntityDefinition $definition,
        Context $context,
        EntityCollection $collection,
        FieldCollection $fields
    ): EntityCollection {
        if ($collection->count() <= 0) {
            return $collection;
        }

        foreach ($fields as $association) {
            if (!$association instanceof AssociationField) {
                continue;
            }

            if ($association instanceof OneToOneAssociationField || $association instanceof ManyToOneAssociationField) {
                $this->loadToOne($association, $context, $collection, $criteria);

                continue;
            }

            if ($association instanceof OneToManyAssociationField) {
                $this->loadOneToMany($criteria, $definition, $association, $context, $collection);

                continue;
            }

            if ($association instanceof ManyToManyAssociationField) {
                $this->loadManyToMany($criteria, $association, $context, $collection);
            }
        }

        foreach ($collection as $struct) {
            $struct->removeExtension(self::INTERNAL_MAPPING_STORAGE);
        }

        return $collection;
    }
}
