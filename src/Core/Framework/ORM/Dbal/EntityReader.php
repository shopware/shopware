<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ORM\Dbal;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\ORM\Entity;
use Shopware\Core\Framework\ORM\EntityCollection;
use Shopware\Core\Framework\ORM\EntityDefinition;
use Shopware\Core\Framework\ORM\Field\AssociationInterface;
use Shopware\Core\Framework\ORM\Field\ChildrenAssociationField;
use Shopware\Core\Framework\ORM\Field\Field;
use Shopware\Core\Framework\ORM\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\ORM\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\ORM\Field\OneToManyAssociationField;
use Shopware\Core\Framework\ORM\Field\SearchKeywordAssociationField;
use Shopware\Core\Framework\ORM\Field\TranslatedField;
use Shopware\Core\Framework\ORM\FieldCollection;
use Shopware\Core\Framework\ORM\Read\EntityReaderInterface;
use Shopware\Core\Framework\ORM\Read\ReadCriteria;
use Shopware\Core\Framework\ORM\Search\Criteria;
use Shopware\Core\Framework\ORM\Search\Parser\SqlQueryParser;
use Shopware\Core\Framework\ORM\Search\Query\TermQuery;
use Shopware\Core\Framework\ORM\Search\Query\TermsQuery;
use Shopware\Core\Framework\ORM\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\ORM\Write\FieldAware\StorageAware;
use Shopware\Core\Framework\ORM\Write\Flag\CascadeDelete;
use Shopware\Core\Framework\ORM\Write\Flag\Deferred;
use Shopware\Core\Framework\ORM\Write\Flag\DelayedLoad;
use Shopware\Core\Framework\ORM\Write\Flag\Extension;
use Shopware\Core\Framework\ORM\Write\Flag\Inherited;
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
        $criteria->setSortings([]);
        $criteria->setQueries([]);

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
        if (empty($criteria->getAllFilters()->getQueries()) && empty($criteria->getIds())) {
            return $collection;
        }

        foreach ($criteria->getAssociations() as $fieldName => $fieldCriteria) {
            $fieldName = str_replace($definition::getEntityName() . '.', '', $fieldName);

            $field = $definition::getFields()->get($fieldName);
            if ($field) {
                $fields->add($definition::getFields()->get($fieldName));
            }
        }

        $rows = $this->fetch($criteria, $definition, $context, $fields, $raw);
        $entities = $this->hydrator->hydrate($entity, $definition, $rows, $definition::getEntityName());
        $collection->fill($entities);

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

        if (empty($criteria->getSortings()) && !empty($criteria->getIds())) {
            $collection->sortByIdArray($criteria->getIds());
        }

        return $collection;
    }

    private function joinBasic(?Criteria $criteria, string $definition, Context $context, string $root, QueryBuilder $query, FieldCollection $fields, bool $raw = false): void
    {
        /** @var EntityDefinition $definition */
        $filtered = $fields->fmap(function (Field $field) {
            if ($field->is(Deferred::class)) {
                return null;
            }

            return $field;
        });

        $parent = null;

        if ($definition::isInheritanceAware() && !$raw) {
            /** @var EntityDefinition|string $definition */
            $parent = $definition::getFields()->get('parent');
            $this->queryHelper->resolveField($parent, $definition, $root, $query, $context);
        }

        /** @var Field $field */
        foreach ($filtered as $field) {
            //translated fields are handled after loop all together
            if ($field instanceof TranslatedField) {
                continue;
            }

            //self references can not be resolved, otherwise we get an endless loop
            if ($field instanceof AssociationInterface && $field->getReferenceClass() === $definition) {
                continue;
            }

            /** @var Field $field */
            if ($field instanceof AssociationInterface && $field->is(Inherited::class)) {
                $query->addSelect(
                    EntityDefinitionQueryHelper::escape($root) . '.' . EntityDefinitionQueryHelper::escape($field->getPropertyName())
                    . ' as ' .
                    EntityDefinitionQueryHelper::escape('_' . $root . '.' . $field->getPropertyName() . '.inherited')
                );
            }

            //many to one associations can be directly fetched in same query
            if ($field instanceof ManyToOneAssociationField) {
                /** @var EntityDefinition|string $reference */
                $reference = $field->getReferenceClass();

                $basics = $reference::getFields()->filterBasic();

                if ($this->shouldBeLoadedDelayed($field, $reference, $basics->getElements())) {
                    continue;
                }

                $this->queryHelper->resolveField($field, $definition, $root, $query, $context);

                $alias = $root . '.' . $field->getPropertyName();

                $accessor = $definition::getEntityName() . '.' . $field->getPropertyName();

                $this->joinBasic($criteria->getAssociation($accessor), $field->getReferenceClass(), $context, $alias, $query, $basics, $raw);

                continue;
            }

            //add sub select for many to many field
            if ($field instanceof ManyToManyAssociationField) {
                $fieldCriteria = $criteria->getAssociation(
                    $definition::getEntityName() . '.' . $field->getPropertyName()
                );

                //requested a paginated, filtered or sorted list
                if ($this->hasCriteriaElements($fieldCriteria)) {
                    continue;
                }

                $this->addManyToManySelect($definition, $root, $field, $query);
                continue;
            }

            //other associations like OneToManyAssociationField fetched lazy by additional query
            if ($field instanceof AssociationInterface) {
                continue;
            }

            /** @var Field $field */
            if ($field instanceof StorageAware && $field->is(Inherited::class) && $parent !== null && !$raw) {
                $parentAlias = $root . '.' . $parent->getPropertyName();
                $child = EntityDefinitionQueryHelper::escape($root) . '.' . EntityDefinitionQueryHelper::escape($field->getStorageName());
                $parentField = EntityDefinitionQueryHelper::escape($parentAlias) . '.' . EntityDefinitionQueryHelper::escape($field->getStorageName());
                $fieldAlias = EntityDefinitionQueryHelper::escape($root . '.' . $field->getPropertyName());

                $query->addSelect(
                    sprintf('COALESCE(%s, %s) as %s', $child, $parentField, $fieldAlias)
                );

                $fieldAlias = EntityDefinitionQueryHelper::escape(
                    '_' . $root . '.' . $field->getPropertyName() . '.' . 'inherited'
                );

                $query->addSelect(
                    sprintf('%s IS NULL as %s', $child, $fieldAlias)
                );
                continue;
            }

            //all other StorageAware fields are stored inside the main entity
            if ($field instanceof StorageAware) {
                /* @var Field $field */
                $query->addSelect(
                    EntityDefinitionQueryHelper::escape($root) . '.' . EntityDefinitionQueryHelper::escape($field->getStorageName())
                    . ' as ' .
                    EntityDefinitionQueryHelper::escape($root . '.' . $field->getPropertyName())
                );
            }
        }

        $translatedFields = $fields->fmap(function (Field $field) {
            if ($field instanceof TranslatedField) {
                return $field;
            }

            return null;
        });

        if (\count($translatedFields) <= 0) {
            return;
        }

        $this->queryHelper->addTranslationSelect($root, $definition, $query, $context, $translatedFields, $raw);
    }

    private function fetch(ReadCriteria $criteria, string $definition, Context $context, FieldCollection $fields, bool $raw): array
    {
        /** @var string|EntityDefinition $definition */
        $table = $definition::getEntityName();

        $query = $this->buildQueryByCriteria(new QueryBuilder($this->connection), $this->queryHelper, $this->parser, $definition, $criteria, $context);

        $this->joinBasic($criteria, $definition, $context, $table, $query, $fields, $raw);

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
        if (!$this->shouldBeLoadedDelayed($association, $reference, $fields->getElements())) {
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

        $fieldCriteria = $criteria->getAssociation($accessor);

        if ($this->hasCriteriaElements($fieldCriteria)) {
            $this->loadManyToManyWithCriteria($fieldCriteria, $association, $context, $collection);

            return;
        }

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

        $tenantField = $definition::getEntityName() . '_tenant_id';

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
            '#property#' => EntityDefinitionQueryHelper::escape($root . '.' . $field->getPropertyName()),
        ];

        $query->addSelect(
            str_replace(
                array_keys($parameters),
                array_values($parameters),
                '(SELECT GROUP_CONCAT(HEX(#alias#.#mapping_reference_column#) SEPARATOR \'||\')
                  FROM #mapping_table# #alias#
                  WHERE #alias#.#mapping_local_column# = #source#' .
                  $versionCondition .
                  ' AND #root#.tenant_id = #alias#.' . $tenantField .
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

    private function shouldBeLoadedDelayed(Field $association, string $definition, array $fields): bool
    {
        if ($association->is(DelayedLoad::class)) {
            return true;
        }

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

            if ($field->is(DelayedLoad::class)) {
                return true;
            }

            $nested = $reference::getFields()->filterBasic()->getElements();
            if ($this->shouldBeLoadedDelayed($field, $reference, $nested)) {
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
            /** @var ArrayStruct $extension */
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
        $fieldCriteria = $criteria->getAssociation(
            $definition::getEntityName() . '.' . $association->getPropertyName()
        );

        if ($association instanceof SearchKeywordAssociationField) {
            $fieldCriteria->addFilter(new TermQuery('search_document.entity', $definition::getEntityName()));
        }

        //association should not be paginated > load data over foreign key condition
        if (!$fieldCriteria->getLimit()) {
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
        $readCriteria->setFilters($fieldCriteria->getAllFilters()->getQueries());
        $readCriteria->setSortings($fieldCriteria->getSortings());
        $readCriteria->setAssociations($fieldCriteria->getAssociations());
        $readCriteria->addFilter(new TermsQuery($propertyAccessor, $ids));

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
        /** @var string|EntityDefinition $definition */
        $propertyName = $this->getDomainName($definition::getEntityName()) . 'Id';
        if ($association instanceof ChildrenAssociationField) {
            $propertyName = 'parentId';
        }

        //build orm property accessor to add field sortings and conditions `customer_address.customerId`
        $propertyAccessor = $association->getReferenceClass()::getEntityName() . '.' . $propertyName;

        //inject sorting for foreign key, otherwise the internal counter wouldn't work `order by customer_address.customer_id, other_sortings`
        $fieldCriteria->setSortings(
            array_merge(
                [new FieldSorting($propertyAccessor, FieldSorting::ASCENDING)],
                $fieldCriteria->getSortings()
            )
        );

        //add terms query to filter reference table to loaded root entities: `customer_address.customerId IN (:loadedIds)`
        $fieldCriteria->addFilter(new TermsQuery($propertyAccessor, array_values($collection->getIds())));

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
        $readCriteria->setAssociations($fieldCriteria->getAssociations());

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
            $extension = $struct->getExtension('many_to_many_storage');

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
        $criteria->setSortings($fieldCriteria->getSortings());
        $criteria->setFilters($fieldCriteria->getFilters()->getQueries());
        $criteria->setLimit($fieldCriteria->getLimit());
        $criteria->setOffset($fieldCriteria->getOffset());
        $criteria->addFilter(new TermsQuery($accessor, $collection->getIds()));

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
            'LOWER(HEX(' . $root . '.' . $localColumn . '))',
            'GROUP_CONCAT(LOWER(HEX(' . $root . '.' . $referenceColumn . ')) ' . $orderBy . ')',
        ]);

        $query->addGroupBy($root . '.' . $localColumn);

        if ($criteria->getLimit()) {
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

        $mapping = $query->execute()->fetchAll(\PDO::FETCH_KEY_PAIR);

        $ids = [];
        foreach ($mapping as &$row) {
            $row = array_filter(explode(',', $row));
            foreach ($row as $id) {
                $ids[] = $id;
            }
        }

        $read = new ReadCriteria($ids);

        $referenceClass = $association->getReferenceDefinition();
        $collectionClass = $referenceClass::getCollectionClass();
        $data = $this->_read($read, $referenceClass, $context, $referenceClass::getStructClass(), new $collectionClass(), $referenceClass::getFields()->filterBasic());

        /** @var Entity $entity */
        foreach ($collection as $entity) {
            $entities = new $collectionClass([]);

            if (array_key_exists($entity->getId(), $mapping)) {
                $x = $mapping[$entity->getId()];
                $entities = $data->getList($x);
                $entities->sortByIdArray($x);
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
                return Uuid::fromHexToBytes($entity->getId());
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

        return $wrapper->execute()->fetchAll(\PDO::FETCH_KEY_PAIR);
    }

    private function getDomainName(string $name)
    {
        return lcfirst(str_replace('_', '', ucwords($name, '_')));
    }

    private function hasCriteriaElements(Criteria $criteria): bool
    {
        if ($criteria->getLimit() !== null) {
            return true;
        }
        if (!empty($criteria->getSortings())) {
            return true;
        }
        if (!empty($criteria->getAllFilters()->getQueries())) {
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
}
