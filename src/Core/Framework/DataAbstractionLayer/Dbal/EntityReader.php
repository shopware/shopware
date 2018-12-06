<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Dbal;

use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\AssociationInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ChildrenAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ParentAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\SearchKeywordAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Read\EntityReaderInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Read\ReadCriteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Parser\SqlQueryParser;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\DataAbstractionLayer\Write\FieldAware\StorageAware;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\Deferred;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\Extension;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\Inherited;
use Shopware\Core\Framework\Doctrine\FetchModeHelper;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\Framework\Struct\Uuid;

/**
 * Reads entities in specify data form (basic, detail, dynamic).
 *
 * Basic => contains all scalar fields of the definition and associations which marked as "loadInBasic=true"
 * Detail => contains all fields, excluded fields which marked with writeOnly
 * Dynamic => allows to specify which fields should be loaded
 */
class EntityReader implements EntityReaderInterface
{
    use CriteriaQueryHelper;

    public const MANY_TO_MANY_EXTENSION_STORAGE = 'many_to_many_storage';

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

    public function __construct(
        Connection $connection,
        EntityHydrator $hydrator,
        EntityDefinitionQueryHelper $queryHelper,
        SqlQueryParser $parser
    ) {
        $this->connection = $connection;
        $this->hydrator = $hydrator;
        $this->queryHelper = $queryHelper;
        $this->parser = $parser;
    }

    public function read(string $definition, ReadCriteria $criteria, Context $context): EntityCollection
    {
        $criteria->resetSorting();
        $criteria->resetQueries();

        /** @var string|EntityDefinition $definition */
        $collectionClass = $definition::getCollectionClass();

        return $this->_read($criteria, $definition, $context, $definition::getStructClass(), new $collectionClass(), $definition::getFields()->filterBasic());
    }

    public function readRaw(string $definition, ReadCriteria $criteria, Context $context): EntityCollection
    {
        /** @var EntityDefinition $definition */
        $collectionClass = EntityCollection::class;

        $structClass = ArrayStruct::class;

        $details = $this->_read(
            $criteria,
            $definition,
            $context,
            $structClass,
            new $collectionClass(),
            $definition::getFields(),
            true
        );

        $this->removeInheritance($definition, $details);

        return $details;
    }

    private function _read(ReadCriteria $criteria, string $definition, Context $context, string $entity, EntityCollection $collection, FieldCollection $fields, bool $raw = false): EntityCollection
    {
        $hasFilters = !empty($criteria->getFilters()) || !empty($criteria->getPostFilters());
        $hasIds = !empty($criteria->getIds());

        if (!$hasFilters && !$hasIds) {
            return $collection;
        }

        /* @var string|EntityDefinition $definition */
        foreach ($criteria->getAssociations() as $fieldName => $fieldCriteria) {
            $fieldName = str_replace(
                [$definition::getEntityName() . '.', 'extensions.'],
                '',
                $fieldName
            );

            $field = $definition::getFields()->get($fieldName);
            if ($field) {
                $fields->add($field);
            }
        }

        $rows = $this->fetch($criteria, $definition, $context, $fields);
        $entities = $this->hydrator->hydrate($entity, $definition, $rows, $definition::getEntityName(), $context);
        $collection->fill($entities);

        if ($collection->count() <= 0) {
            return $collection;
        }

        /** @var EntityDefinition $reference */
        $associations = $fields->filterInstance(ManyToOneAssociationField::class);
        /** @var ManyToOneAssociationField[] $associations */
        foreach ($associations as $association) {
            $this->loadManyToOne($definition, $association, $context, $collection);
        }

        /** @var OneToManyAssociationField[] $associations */
        $associations = $fields->filterInstance(OneToManyAssociationField::class);
        foreach ($associations as $association) {
            $this->loadOneToMany($criteria, $definition, $association, $context, $collection);
        }

        /** @var ManyToManyAssociationField[] $associations */
        $associations = $fields->filterInstance(ManyToManyAssociationField::class);
        foreach ($associations as $association) {
            $this->loadManyToMany($definition, $criteria, $association, $context, $collection);
        }

        /** @var Entity $struct */
        foreach ($collection as $struct) {
            $struct->removeExtension(self::MANY_TO_MANY_EXTENSION_STORAGE);
        }

        if ($hasIds && empty($criteria->getSorting())) {
            $collection->sortByIdArray($criteria->getIds());
        }

        return $collection;
    }

    private function joinBasic(
        string $definition,
        Context $context,
        string $root,
        QueryBuilder $query,
        FieldCollection $fields,
        Criteria $criteria = null
    ): void {
        /** @var EntityDefinition $definition */
        $filtered = $fields->fmap(function (Field $field) {
            if ($field->is(Deferred::class)) {
                return null;
            }

            return $field;
        });

        $parentAssociation = null;

        if ($definition::isInheritanceAware()) {
            /** @var EntityDefinition|string $definition */
            $parentAssociation = $definition::getFields()->get('parent');
            $this->queryHelper->resolveField($parentAssociation, $definition, $root, $query, $context);
        }

        /** @var Field $field */
        foreach ($filtered as $field) {
            //translated fields are handled after loop all together
            if ($field instanceof TranslatedField) {
                $this->queryHelper->resolveField($field, $definition, $root, $query, $context);
                continue;
            }

            //self references can not be resolved, otherwise we get an endless loop
            if ($field instanceof AssociationInterface && $field->getReferenceClass() === $definition && !$field instanceof ParentAssociationField) {
                continue;
            }

            /** @var Field $field */
            if ($field instanceof AssociationInterface && $field->is(Inherited::class)) {
                if ($field instanceof ManyToOneAssociationField) {
                    //tables with inherited associations containing a column with the association name, this columns contains the uuid of the owner of this association
                    $query->addSelect(
                        EntityDefinitionQueryHelper::escape($root) . '.' . EntityDefinitionQueryHelper::escape($field->getStorageName())
                        . ' as ' .
                        EntityDefinitionQueryHelper::escape($root . '.' . $field->getPropertyName() . '.owner')
                    );
                } else {
                    //tables with inherited associations containing a column with the association name, this columns contains the uuid of the owner of this association
                    $query->addSelect(
                        EntityDefinitionQueryHelper::escape($root) . '.' . EntityDefinitionQueryHelper::escape($field->getPropertyName())
                        . ' as ' .
                        EntityDefinitionQueryHelper::escape($root . '.' . $field->getPropertyName() . '.owner')
                    );
                }
            }

            $accessor = $definition::getEntityName() . '.' . $field->getPropertyName();

            //many to one associations can be directly fetched in same query
            if ($field instanceof ManyToOneAssociationField) {
                /** @var EntityDefinition|string $reference */
                $reference = $field->getReferenceClass();

                $basics = $reference::getFields()->filterBasic();

                if ($this->shouldBeLoadedDelayed($reference, $basics->getElements())) {
                    continue;
                }

                $this->queryHelper->resolveField($field, $definition, $root, $query, $context);

                $alias = $root . '.' . $field->getPropertyName();

                $joinCriteria = null;
                if ($criteria && $criteria->hasAssociation($accessor)) {
                    $joinCriteria = $criteria->getAssociation($accessor);
                }

                $this->joinBasic($field->getReferenceClass(), $context, $alias, $query, $basics, $joinCriteria);

                continue;
            }

            //add sub select for many to many field
            if ($field instanceof ManyToManyAssociationField) {
                if ($this->isAssociationRestricted($criteria, $accessor)) {
                    continue;
                }

                //requested a paginated, filtered or sorted list

                $this->addManyToManySelect($definition, $root, $field, $query);
                continue;
            }

            //other associations like OneToManyAssociationField fetched lazy by additional query
            if ($field instanceof AssociationInterface) {
                continue;
            }

            /** @var Field $field */
            if ($field instanceof StorageAware && $field->is(Inherited::class) && $parentAssociation !== null) {
                $parentAlias = $root . '.' . $parentAssociation->getPropertyName();

                //contains the field accessor for the child value (eg. `product.name`.`name`)
                $childAccessor = EntityDefinitionQueryHelper::escape($root) . '.' . EntityDefinitionQueryHelper::escape($field->getStorageName());

                //contains the field accessor for the parent value (eg. `product.parent`.`name`)
                $parentAccessor = EntityDefinitionQueryHelper::escape($parentAlias) . '.' . EntityDefinitionQueryHelper::escape($field->getStorageName());

                //contains the alias for the resolved field (eg. `product.name`)
                $fieldAlias = EntityDefinitionQueryHelper::escape($root . '.' . $field->getPropertyName());

                //add selection for resolved parent-child inheritance field
                $query->addSelect(sprintf('COALESCE(%s, %s) as %s', $childAccessor, $parentAccessor, $fieldAlias));

                //add selection for raw child value
                $fieldAlias = EntityDefinitionQueryHelper::escape($root . '.' . $field->getPropertyName() . '.raw');
                $query->addSelect(sprintf('%s as %s', $childAccessor, $fieldAlias));

                continue;
            }

            //all other StorageAware fields are stored inside the main entity
            if ($field instanceof StorageAware) {
                /* @var StorageAware|Field $field */
                $query->addSelect(
                    EntityDefinitionQueryHelper::escape($root) . '.' . EntityDefinitionQueryHelper::escape($field->getStorageName())
                    . ' as ' .
                    EntityDefinitionQueryHelper::escape($root . '.' . $field->getPropertyName())
                );
            }
        }

        $translationDefinition = $definition::getTranslationDefinitionClass();

        if ($translationDefinition === null) {
            return;
        }

        $this->queryHelper->addTranslationSelect($root, $definition, $query, $context);
    }

    private function fetch(ReadCriteria $criteria, string $definition, Context $context, FieldCollection $fields): array
    {
        /** @var string|EntityDefinition $definition */
        $table = $definition::getEntityName();

        $query = $this->buildQueryByCriteria(new QueryBuilder($this->connection), $this->queryHelper, $this->parser, $definition, $criteria, $context);

        $this->joinBasic($definition, $context, $table, $query, $fields, $criteria);

        if (!empty($criteria->getIds())) {
            $bytes = array_map(function (string $id) {
                return Uuid::fromStringToBytes($id);
            }, $criteria->getIds());

            $query->andWhere(EntityDefinitionQueryHelper::escape($table) . '.`id` IN (:ids)');
            $query->setParameter('ids', array_values($bytes), Connection::PARAM_STR_ARRAY);
        }

        return $query->execute()->fetchAll();
    }

    private function loadManyToOne(string $definition, ManyToOneAssociationField $association, Context $context, EntityCollection $collection): void
    {
        $reference = $association->getReferenceClass();

        $fields = $reference::getFields()->filterBasic();
        if (!$this->shouldBeLoadedDelayed($reference, $fields->getElements())) {
            return;
        }

        /** @var string|EntityDefinition $definition */
        $field = $definition::getFields()->getByStorageName($association->getStorageName());
        $ids = $collection->map(function (Entity $entity) use ($field) {
            return $entity->get($field->getPropertyName());
        });

        $ids = array_filter($ids);

        $referenceClass = $association->getReferenceClass();

        /** @var string|EntityDefinition $definition */
        $collectionClass = $referenceClass::getCollectionClass();
        $data = $this->_read(new ReadCriteria($ids), $referenceClass, $context, $referenceClass::getStructClass(), new $collectionClass(), $referenceClass::getFields()->filterBasic());

        /** @var Entity $struct */
        foreach ($collection as $struct) {
            /** @var string $id */
            $id = $struct->get($field->getPropertyName());

            if (!$id) {
                continue;
            }

            if ($association->is(Extension::class)) {
                $struct->addExtension($association->getPropertyName(), $data->get($id));
                continue;
            }

            $struct->assign([
                $association->getPropertyName() => $data->get($id),
            ]);
        }
    }

    private function loadManyToMany(string $definition, Criteria $criteria, ManyToManyAssociationField $association, Context $context, EntityCollection $collection): void
    {
        /** @var string|EntityDefinition $definition */
        $accessor = $definition::getEntityName() . '.' . $association->getPropertyName();

        //check if the requested criteria is restricted (limit, offset, sorting, filtering)
        if ($this->isAssociationRestricted($criteria, $accessor)) {
            //if restricted load paginated list of many to many
            $this->loadManyToManyWithCriteria(
                $criteria->getAssociation($accessor),
                $association,
                $context,
                $collection
            );

            return;
        }

        //otherwise the association is loaded in the root query of the entity as sub select which contains all ids
        //the ids are extracted in the entity hydrator (see: \Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityHydrator::extractManyToManyIds)
        $this->loadManyToManyOverExtension($association, $context, $collection);
    }

    private function addManyToManySelect(string $definition, string $root, ManyToManyAssociationField $field, QueryBuilder $query): void
    {
        /** @var EntityDefinition $mapping */
        $mapping = $field->getMappingDefinition();

        $versionCondition = '';
        /** @var string|EntityDefinition $definition */
        if ($mapping::isVersionAware() && $definition::isVersionAware() && $field->is(CascadeDelete::class)) {
            $versionField = $definition::getEntityName() . '_version_id';
            $versionCondition = ' AND #alias#.' . $versionField . ' = #root#.version_id';
        }

        $catalogCondition = '';
        if ($mapping::isCatalogAware() && $definition::isCatalogAware()) {
            $catalogCondition = ' AND #alias#.catalog_id = #root#.catalog_id';
        }

        $source = EntityDefinitionQueryHelper::escape($root) . '.' . EntityDefinitionQueryHelper::escape($field->getLocalField());
        if ($field->is(Inherited::class)) {
            $source = EntityDefinitionQueryHelper::escape($root) . '.' . EntityDefinitionQueryHelper::escape($field->getPropertyName());
        }

        $parameters = [
            '#alias#' => EntityDefinitionQueryHelper::escape($root . '.' . $field->getPropertyName() . '.mapping'),
            '#mapping_reference_column#' => EntityDefinitionQueryHelper::escape($field->getMappingReferenceColumn()),
            '#mapping_table#' => EntityDefinitionQueryHelper::escape($mapping::getEntityName()),
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
                  WHERE #alias#.#mapping_local_column# = #source#' .
                  $versionCondition .
                  $catalogCondition .
                  ' ) as #property#'
            )
        );
    }

    private function collectManyToManyIds(EntityCollection $collection, AssociationInterface $association): array
    {
        $ids = [];
        /** @var Field $association */
        $property = $association->getPropertyName();
        foreach ($collection as $struct) {
            $tmp = $struct->getExtension(self::MANY_TO_MANY_EXTENSION_STORAGE)->get($property);
            foreach ($tmp as $id) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    private function shouldBeLoadedDelayed(string $definition, array $fields): bool
    {
        /** @var Field $field */
        foreach ($fields as $field) {
            if (!$field instanceof AssociationInterface) {
                continue;
            }

            if ($field instanceof ManyToManyAssociationField || $field instanceof OneToManyAssociationField) {
                return true;
            }

            /** @var ManyToOneAssociationField $field */
            $reference = $field->getReferenceClass();

            if ($reference === $definition) {
                continue;
            }

            if ($field->is(Deferred::class)) {
                continue;
            }

            $nested = $reference::getFields()->filterBasic()->getElements();
            if ($this->shouldBeLoadedDelayed($reference, $nested)) {
                return true;
            }
        }

        return false;
    }

    private function removeInheritance(string $definition, $details): void
    {
        /** @var string|EntityDefinition $definition */
        $inherited = $definition::getFields()
            ->filterInstance(AssociationInterface::class)
            ->filterByFlag(Inherited::class);

        /** @var ArrayStruct $detail */
        foreach ($details as $detail) {
            /** @var ArrayStruct|null $extension */
            $extension = $detail->getExtension('inherited');

            if (!$extension) {
                continue;
            }

            foreach ($inherited as $association) {
                if (!$extension->offsetExists($association->getPropertyName())) {
                    continue;
                }
                if (!$extension->get($association->getPropertyName())) {
                    continue;
                }
                $detail->offsetUnset($association->getPropertyName());
            }
        }
    }

    private function loadOneToMany(ReadCriteria $criteria, string $definition, OneToManyAssociationField $association, Context $context, EntityCollection $collection): void
    {
        /** @var string|EntityDefinition $definition */
        $accessor = $definition::getEntityName() . '.' . $association->getPropertyName();
        if (!$criteria->hasAssociation($accessor)) {
            $fieldCriteria = new Criteria();
        } else {
            $fieldCriteria = $criteria->getAssociation($accessor);
        }

        if ($association instanceof SearchKeywordAssociationField) {
            $fieldCriteria->addFilter(new EqualsFilter('search_document.entity', $definition::getEntityName()));
        }

        //association should not be paginated > load data over foreign key condition
        if ($fieldCriteria->getLimit() === null) {
            $this->loadOneToManyWithoutPagination($definition, $association, $context, $collection, $fieldCriteria);

            return;
        }

        //load association paginated > use internal counter loops
        $this->loadOneToManyWithPagination($definition, $association, $context, $collection, $fieldCriteria);
    }

    private function loadOneToManyWithoutPagination(string $definition, OneToManyAssociationField $association, Context $context, EntityCollection $collection, Criteria $fieldCriteria): void
    {
        $ref = $association->getReferenceClass()::getFields()->getByStorageName(
            $association->getReferenceField()
        );

        /** @var string|EntityDefinition $definition */
        $propertyName = $ref->getPropertyName();
        if ($association instanceof ChildrenAssociationField) {
            $propertyName = 'parentId';
        }

        //build orm property accessor to add field sortings and conditions `customer_address.customerId`
        $propertyAccessor = $association->getReferenceClass()::getEntityName() . '.' . $propertyName;

        $ids = array_values($collection->getIds());

        $isInheritanceAware = $definition::isInheritanceAware();

        if ($isInheritanceAware) {
            $parentIds = $collection->map(function (Entity $entity) {
                return $entity->get('parentId');
            });

            $parentIds = array_values(array_filter($parentIds));

            $ids = array_unique(array_merge($ids, $parentIds));
        }

        //create new read criteria for the association without pre fetched ids
        $readCriteria = new ReadCriteria([]);
        $readCriteria->addFilter(...$fieldCriteria->getFilters());
        $readCriteria->addPostFilter(...$fieldCriteria->getPostFilters());
        $readCriteria->addSorting(...$fieldCriteria->getSorting());
        $readCriteria->addFilter(new EqualsAnyFilter($propertyAccessor, $ids));

        foreach ($fieldCriteria->getAssociations() as $key => $associationCriteria) {
            $readCriteria->addAssociation($key, $associationCriteria);
        }

        $referenceClass = $association->getReferenceClass();
        $collectionClass = $referenceClass::getCollectionClass();
        $data = $this->_read($readCriteria, $referenceClass, $context, $referenceClass::getStructClass(), new $collectionClass(), $referenceClass::getFields()->filterBasic());

        //assign loaded data to root entities
        foreach ($collection as $entity) {
            $structData = $data->filterByProperty($propertyName, $entity->getId());

            if ($structData->count() === 0 && $isInheritanceAware) {
                $structData = $data->filterByProperty($propertyName, $entity->get('parentId'));
            }

            if ($association->is(Extension::class)) {
                $entity->addExtension($association->getPropertyName(), $structData);
                continue;
            }

            $entity->assign([
                $association->getPropertyName() => $structData,
            ]);
        }
    }

    private function loadOneToManyWithPagination(string $definition, OneToManyAssociationField $association, Context $context, EntityCollection $collection, Criteria $fieldCriteria): void
    {
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
            $associationIds = array_filter(explode(',', (string) $associationIds));
            foreach ($associationIds as $associationId) {
                $ids[] = $associationId;
            }
        }

        //create new read criteria for the association
        $readCriteria = new ReadCriteria($ids);
        foreach ($fieldCriteria->getAssociations() as $key => $associationCriteria) {
            $readCriteria->addAssociation($key, $associationCriteria);
        }

        $referenceClass = $association->getReferenceClass();
        $collectionClass = $referenceClass::getCollectionClass();
        $data = $this->_read($readCriteria, $referenceClass, $context, $referenceClass::getStructClass(), new $collectionClass(), $referenceClass::getFields()->filterBasic());

        //assign loaded reference collections to root entities
        foreach ($collection as $entity) {
            $mappingIds = $mapping[$entity->getId()];
            $mappingIds = array_filter(explode(',', $mappingIds));

            $structData = $data->getList($mappingIds);

            if ($association->is(Extension::class)) {
                $entity->addExtension($association->getPropertyName(), $structData);
                continue;
            }

            $entity->assign([
                $association->getPropertyName() => $structData,
            ]);
        }
    }

    private function loadManyToManyOverExtension(ManyToManyAssociationField $association, Context $context, EntityCollection $collection): void
    {
        //collect all ids of many to many association which already stored inside the struct instances
        $ids = $this->collectManyToManyIds($collection, $association);

        $referenceClass = $association->getReferenceDefinition();
        $collectionClass = $referenceClass::getCollectionClass();
        $data = $this->_read(new ReadCriteria($ids), $referenceClass, $context, $referenceClass::getStructClass(), new $collectionClass(), $referenceClass::getFields()->filterBasic());

        /** @var Entity $struct */
        foreach ($collection as $struct) {
            /** @var ArrayStruct $extension */
            $extension = $struct->getExtension(self::MANY_TO_MANY_EXTENSION_STORAGE);

            //use assign function to avoid setter name building
            $structData = $data->getList($extension->get($association->getPropertyName()));

            if ($association->is(Extension::class)) {
                $struct->addExtension($association->getPropertyName(), $structData);
                continue;
            }

            $struct->assign([
                $association->getPropertyName() => $structData,
            ]);
        }
    }

    private function loadManyToManyWithCriteria(Criteria $fieldCriteria, ManyToManyAssociationField $association, Context $context, EntityCollection $collection): void
    {
        /** @var string|EntityDefinition $definition */
        $fields = $association->getReferenceDefinition()::getFields();
        $reference = null;
        foreach ($fields as $field) {
            if (!$field instanceof ManyToManyAssociationField) {
                continue;
            }

            if ($field->getReferenceClass() !== $association->getReferenceClass()) {
                continue;
            }

            $reference = $field;
            break;
        }

        if (!$reference) {
            throw new \RuntimeException(sprintf('No inverse many to many association found, for association %s', $association->getPropertyName()));
        }

        //build inverse accessor `product.categories.id`
        $accessor = $association->getReferenceDefinition()::getEntityName() . '.' . $reference->getPropertyName() . '.id';

        $criteria = new ReadCriteria([]);
        $criteria->addSorting(...$fieldCriteria->getSorting());
        $criteria->addFilter(...$fieldCriteria->getFilters());
        $criteria->addPostFilter(...$fieldCriteria->getPostFilters());
        $criteria->setLimit($fieldCriteria->getLimit());
        $criteria->setOffset($fieldCriteria->getOffset());
        $criteria->addFilter(new EqualsAnyFilter($accessor, $collection->getIds()));

        $root = EntityDefinitionQueryHelper::escape($association->getReferenceDefinition()::getEntityName() . '.' . $reference->getPropertyName() . '.mapping');
        $query = $this->buildQueryByCriteria(new QueryBuilder($this->connection), $this->queryHelper, $this->parser, $association->getReferenceDefinition(), $criteria, $context);

        $localColumn = EntityDefinitionQueryHelper::escape($association->getMappingLocalColumn());
        $referenceColumn = EntityDefinitionQueryHelper::escape($association->getMappingReferenceColumn());

        $orderBy = '';
        $parts = $query->getQueryPart('orderBy');
        if (!empty($parts)) {
            $orderBy = ' ORDER BY ' . implode(', ', $parts);
            $query->resetQueryPart('orderBy');
        }

        $query->select([
            'LOWER(HEX(' . $root . '.' . $localColumn . ')) as `key`',
            'GROUP_CONCAT(LOWER(HEX(' . $root . '.' . $referenceColumn . ')) ' . $orderBy . ') as `value`',
        ]);

        $query->addGroupBy($root . '.' . $localColumn);

        if ($criteria->getLimit() !== null) {
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
            $query->setParameter('limit', $criteria->getLimit());

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

        $read = new ReadCriteria($ids);

        foreach ($fieldCriteria->getAssociations() as $fieldName => $associationCriteria) {
            $read->addAssociation($fieldName, $associationCriteria);
        }

        $referenceClass = $association->getReferenceDefinition();
        $collectionClass = $referenceClass::getCollectionClass();
        $data = $this->_read($read, $referenceClass, $context, $referenceClass::getStructClass(), new $collectionClass(), $referenceClass::getFields()->filterBasic());

        /** @var Entity $entity */
        foreach ($collection as $entity) {
            $entities = new $collectionClass([]);

            if (array_key_exists($entity->getUniqueIdentifier(), $mapping)) {
                $x = $mapping[$entity->getUniqueIdentifier()];
                $entities = $data->getList($x);
                $entities->sortByIdArray($x);
            }

            if ($association->is(Extension::class)) {
                $entity->addExtension($association->getPropertyName(), $entities);
                continue;
            }

            $entity->assign([
                $association->getPropertyName() => $entities,
            ]);
        }
    }

    private function fetchPaginatedOneToManyMapping(string $definition, OneToManyAssociationField $association, Context $context, EntityCollection $collection, Criteria $fieldCriteria): array
    {
        /** @var string|EntityDefinition $definition */

        //build query based on provided association criteria (sortings, search, filter)
        $query = $this->buildQueryByCriteria(new QueryBuilder($this->connection), $this->queryHelper, $this->parser, $association->getReferenceClass(), $fieldCriteria, $context);

        $foreignKey = $definition::getEntityName() . '_id';

        //build sql accessor for foreign key field in reference table `customer_address.customer_id`
        $sqlAccessor = EntityDefinitionQueryHelper::escape($association->getReferenceClass()::getEntityName()) . '.' . EntityDefinitionQueryHelper::escape($foreignKey);

        $query->select(
            [
                //build select with an internal counter loop, the counter loop will be reset if the foreign key changed (this is the reason for the sorting inject above)
                '@n:=IF(@c=' . $sqlAccessor . ', @n+1, IF(@c:=' . $sqlAccessor . ',1,1)) as id_count',

                //add select for foreign key for join condition
                $sqlAccessor,

                //add primary key select to group concat them
                EntityDefinitionQueryHelper::escape($association->getReferenceClass()::getEntityName()) . '.id',
            ]
        );

        $root = EntityDefinitionQueryHelper::escape($definition::getEntityName());

        //create a wrapper query which select the root primary key and the grouped reference ids
        $wrapper = $this->connection->createQueryBuilder();
        $wrapper->select(
            [
                'LOWER(HEX(' . $root . '.id)) as id',
                'GROUP_CONCAT(LOWER(HEX(child.id))) as ids',
            ]
        );

        $wrapper->from($root, $root);

        //wrap query into a sub select to restrict the association count from the outer query
        $wrapper->leftJoin(
            $root,
            '(' . $query->getSQL() . ')',
            'child',
            'child.' . $foreignKey . ' = ' . $root . '.id AND id_count <= :childCount'
        );

        //add group by to concat all association ids for each root
        $wrapper->addGroupBy($root . '.id');

        //filter result to loaded root entities
        $wrapper->andWhere($root . '.id IN (:rootIds)');

        $bytes = $collection->map(
            function (Entity $entity) {
                return Uuid::fromHexToBytes($entity->getUniqueIdentifier());
            }
        );

        $wrapper->setParameter('rootIds', $bytes, Connection::PARAM_STR_ARRAY);
        $wrapper->setParameter('childCount', $fieldCriteria->getLimit());
        foreach ($query->getParameters() as $key => $value) {
            $type = $query->getParameterType($key);
            $wrapper->setParameter($key, $value, $type);
        }

        //initials the cursor and loop counter, pdo do not allow to execute SET and SELECT in one statement
        $this->connection->executeQuery('SET @n = 0; SET @c = null;');

        $rows = $wrapper->execute()->fetchAll();

        return FetchModeHelper::keyPair($rows);
    }

    private function hasCriteriaElements(Criteria $criteria): bool
    {
        if ($criteria->getLimit() !== null) {
            return true;
        }

        if (!empty($criteria->getSorting())) {
            return true;
        }

        if (!empty($criteria->getFilters())) {
            return true;
        }

        if (!empty($criteria->getPostFilters())) {
            return true;
        }

        if (!empty($criteria->getAssociations())) {
            return true;
        }

        return false;
    }

    private function buildManyToManyLimitQuery(ManyToManyAssociationField $association): QueryBuilder
    {
        $table = EntityDefinitionQueryHelper::escape($association->getMappingDefinition()::getEntityName());

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

    private function buildOneToManyPropertyAccessor(string $definition, OneToManyAssociationField $association): string
    {
        if ($association instanceof ChildrenAssociationField) {
            return $association->getReferenceClass()::getEntityName() . '.parentId';
        }

        $fields = $association->getReferenceClass()::getFields()->getElements();
        foreach ($fields as $field) {
            if (!$field instanceof FkField) {
                continue;
            }
            if ($field->getReferenceClass() !== $definition) {
                continue;
            }

            return $association->getReferenceClass()::getEntityName() . '.' . $field->getPropertyName();
        }

        throw new \RuntimeException(
            sprintf(
                'Fk field for association %s not found in definition %s',
                $association->getPropertyName(),
                $association->getReferenceClass()::getEntityName()
            )
        );
    }

    private function isAssociationRestricted(?Criteria $criteria, $accessor): bool
    {
        if ($criteria === null) {
            return false;
        }

        if (!$criteria->hasAssociation($accessor)) {
            return false;
        }

        $fieldCriteria = $criteria->getAssociation($accessor);

        return
            $fieldCriteria->getOffset() !== null
            ||
            $fieldCriteria->getLimit() !== null
            ||
            !empty($fieldCriteria->getSorting())
            ||
            !empty($fieldCriteria->getFilters())
            ||
            !empty($fieldCriteria->getPostFilters())
            ||
            !empty($fieldCriteria->getAssociations())
        ;
    }
}
