<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Dbal;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Exception\ParentAssociationCanNotBeFetched;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\AssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ChildrenAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Extension;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Inherited;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
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
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\ArrayEntity;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\Framework\Uuid\Uuid;
use function array_filter;

/**
 * @internal
 */
#[Package('core')]
class EntityReader implements EntityReaderInterface
{
    final public const INTERNAL_MAPPING_STORAGE = 'internal_mapping_storage';
    final public const FOREIGN_KEYS = 'foreignKeys';
    final public const MANY_TO_MANY_LIMIT_QUERY = 'many_to_many_limit_query';

    public function __construct(
        private readonly Connection $connection,
        private readonly EntityHydrator $hydrator,
        private readonly EntityDefinitionQueryHelper $queryHelper,
        private readonly SqlQueryParser $parser,
        private readonly CriteriaQueryBuilder $criteriaQueryBuilder,
        private readonly LoggerInterface $logger,
        private readonly CriteriaFieldsResolver $criteriaFieldsResolver
    ) {
    }

    /**
     * @return EntityCollection<Entity>
     */
    public function read(EntityDefinition $definition, Criteria $criteria, Context $context): EntityCollection
    {
        $criteria->resetSorting();
        $criteria->resetQueries();

        /** @var EntityCollection<Entity> $collectionClass */
        $collectionClass = $definition->getCollectionClass();

        $fields = $this->criteriaFieldsResolver->resolve($criteria, $definition);

        return $this->_read(
            $criteria,
            $definition,
            $context,
            new $collectionClass(),
            $definition->getFields()->getBasicFields(),
            true,
            $fields
        );
    }

    protected function getParser(): SqlQueryParser
    {
        return $this->parser;
    }

    /**
     * @param EntityCollection<Entity> $collection
     * @param array<string, mixed> $partial
     *
     * @return EntityCollection<Entity>
     */
    private function _read(
        Criteria $criteria,
        EntityDefinition $definition,
        Context $context,
        EntityCollection $collection,
        FieldCollection $fields,
        bool $performEmptySearch = false,
        array $partial = []
    ): EntityCollection {
        $hasFilters = !empty($criteria->getFilters()) || !empty($criteria->getPostFilters());
        $hasIds = !empty($criteria->getIds());

        if (!$performEmptySearch && !$hasFilters && !$hasIds) {
            return $collection;
        }

        if ($partial !== []) {
            $fields = $definition->getFields()->filter(function (Field $field) use (&$partial) {
                if ($field->getFlag(PrimaryKey::class)) {
                    $partial[$field->getPropertyName()] = [];

                    return true;
                }

                return isset($partial[$field->getPropertyName()]);
            });
        }

        // always add the criteria fields to the collection, otherwise we have conflicts between criteria.fields and criteria.association logic
        $fields = $this->addAssociationFieldsToCriteria($criteria, $definition, $fields);

        if ($definition->isInheritanceAware() && $criteria->hasAssociation('parent')) {
            throw new ParentAssociationCanNotBeFetched();
        }

        $rows = $this->fetch($criteria, $definition, $context, $fields, $partial);

        $collection = $this->hydrator->hydrate($collection, $definition->getEntityClass(), $definition, $rows, $definition->getEntityName(), $context, $partial);

        $collection = $this->fetchAssociations($criteria, $definition, $context, $collection, $fields, $partial);

        $hasIds = !empty($criteria->getIds());
        if ($hasIds && empty($criteria->getSorting())) {
            $collection->sortByIdArray($criteria->getIds());
        }

        return $collection;
    }

    /**
     * @param array<string, mixed> $partial
     */
    private function joinBasic(
        EntityDefinition $definition,
        Context $context,
        string $root,
        QueryBuilder $query,
        FieldCollection $fields,
        ?Criteria $criteria = null,
        array $partial = []
    ): void {
        $isPartial = $partial !== [];
        $filtered = $fields->filter(static function (Field $field) use ($isPartial, $partial) {
            if ($field->is(Runtime::class)) {
                return false;
            }

            if (!$isPartial || $field->getFlag(PrimaryKey::class)) {
                return true;
            }

            return isset($partial[$field->getPropertyName()]);
        });

        $parentAssociation = null;

        if ($definition->isInheritanceAware() && $context->considerInheritance()) {
            $parentAssociation = $definition->getFields()->get('parent');

            if ($parentAssociation !== null) {
                $this->queryHelper->resolveField($parentAssociation, $definition, $root, $query, $context);
            }
        }

        $addTranslation = false;

        /** @var Field $field */
        foreach ($filtered as $field) {
            //translated fields are handled after loop all together
            if ($field instanceof TranslatedField) {
                $this->queryHelper->resolveField($field, $definition, $root, $query, $context);

                $addTranslation = true;

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

                $this->joinBasic($reference, $context, $alias, $query, $basics, $joinCriteria, $partial[$field->getPropertyName()] ?? []);

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

        if ($addTranslation) {
            $this->queryHelper->addTranslationSelect($root, $definition, $query, $context, $partial);
        }
    }

    /**
     * @param array<string, mixed> $partial
     *
     * @return list<array<string, mixed>>
     */
    private function fetch(Criteria $criteria, EntityDefinition $definition, Context $context, FieldCollection $fields, array $partial = []): array
    {
        $table = $definition->getEntityName();

        $query = $this->criteriaQueryBuilder->build(
            new QueryBuilder($this->connection),
            $definition,
            $criteria,
            $context
        );

        $this->joinBasic($definition, $context, $table, $query, $fields, $criteria, $partial);

        if (!empty($criteria->getIds())) {
            $this->queryHelper->addIdCondition($criteria, $definition, $query);
        }

        if ($criteria->getTitle()) {
            $query->setTitle($criteria->getTitle() . '::read');
        }

        return $query->executeQuery()->fetchAllAssociative();
    }

    /**
     * @param EntityCollection<Entity> $collection
     * @param array<string, mixed> $partial
     */
    private function loadManyToMany(
        Criteria $criteria,
        ManyToManyAssociationField $association,
        Context $context,
        EntityCollection $collection,
        array $partial
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
            $this->loadManyToManyWithCriteria($associationCriteria, $association, $context, $collection, $partial);

            return;
        }

        //otherwise the association is loaded in the root query of the entity as sub select which contains all ids
        //the ids are extracted in the entity hydrator (see: \Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityHydrator::extractManyToManyIds)
        $this->loadManyToManyOverExtension($associationCriteria, $association, $context, $collection, $partial);
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

    /**
     * @param EntityCollection<Entity> $collection
     *
     * @return array<string>
     */
    private function collectManyToManyIds(EntityCollection $collection, AssociationField $association): array
    {
        $ids = [];
        $property = $association->getPropertyName();
        /** @var Entity $struct */
        foreach ($collection as $struct) {
            /** @var ArrayStruct<string, mixed> $ext */
            $ext = $struct->getExtension(self::INTERNAL_MAPPING_STORAGE);
            /** @var array<string> $tmp */
            $tmp = $ext->get($property);
            foreach ($tmp as $id) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    /**
     * @param EntityCollection<Entity> $collection
     * @param array<string, mixed> $partial
     */
    private function loadOneToMany(
        Criteria $criteria,
        EntityDefinition $definition,
        OneToManyAssociationField $association,
        Context $context,
        EntityCollection $collection,
        array $partial
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
            $this->loadOneToManyWithoutPagination($definition, $association, $context, $collection, $fieldCriteria, $partial);

            return;
        }

        //load association paginated > use internal counter loops
        $this->loadOneToManyWithPagination($definition, $association, $context, $collection, $fieldCriteria, $partial);
    }

    /**
     * @param EntityCollection<Entity> $collection
     * @param array<string, mixed> $partial
     */
    private function loadOneToManyWithoutPagination(
        EntityDefinition $definition,
        OneToManyAssociationField $association,
        Context $context,
        EntityCollection $collection,
        Criteria $fieldCriteria,
        array $partial
    ): void {
        $ref = $association->getReferenceDefinition()->getFields()->getByStorageName(
            $association->getReferenceField()
        );

        \assert($ref instanceof Field);

        $propertyName = $ref->getPropertyName();
        if ($association instanceof ChildrenAssociationField) {
            $propertyName = 'parentId';
        }

        //build orm property accessor to add field sortings and conditions `customer_address.customerId`
        $propertyAccessor = $association->getReferenceDefinition()->getEntityName() . '.' . $propertyName;

        $ids = array_values($collection->getIds());

        $isInheritanceAware = $definition->isInheritanceAware() && $context->considerInheritance();

        if ($isInheritanceAware) {
            $parentIds = array_values(array_filter($collection->map(fn (Entity $entity) => $entity->get('parentId'))));

            $ids = array_unique([...$ids, ...$parentIds]);
        }

        $fieldCriteria->addFilter(new EqualsAnyFilter($propertyAccessor, $ids));

        $referenceClass = $association->getReferenceDefinition();
        /** @var EntityCollection<Entity> $collectionClass */
        $collectionClass = $referenceClass->getCollectionClass();

        if ($partial !== []) {
            // Make sure our collection index will be loaded
            $partial[$propertyName] = [];
            $collectionClass = EntityCollection::class;
        }

        $data = $this->_read(
            $fieldCriteria,
            $referenceClass,
            $context,
            new $collectionClass(),
            $referenceClass->getFields()->getBasicFields(),
            false,
            $partial
        );

        $grouped = [];
        foreach ($data as $entity) {
            $fk = $entity->get($propertyName);

            $grouped[$fk][] = $entity;
        }

        //assign loaded data to root entities
        foreach ($collection as $entity) {
            $structData = new $collectionClass();
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
            $structData = new $collectionClass();
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

    /**
     * @param EntityCollection<Entity> $collection
     * @param array<string, mixed> $partial
     */
    private function loadOneToManyWithPagination(
        EntityDefinition $definition,
        OneToManyAssociationField $association,
        Context $context,
        EntityCollection $collection,
        Criteria $fieldCriteria,
        array $partial
    ): void {
        $isPartial = $partial !== [];

        $propertyAccessor = $this->buildOneToManyPropertyAccessor($definition, $association);

        // inject sorting for foreign key, otherwise the internal counter wouldn't work `order by customer_address.customer_id, other_sortings`
        $sorting = array_merge(
            [new FieldSorting($propertyAccessor, FieldSorting::ASCENDING)],
            $fieldCriteria->getSorting()
        );

        $fieldCriteria->resetSorting();
        $fieldCriteria->addSorting(...$sorting);

        $ids = array_values($collection->getIds());

        if ($isPartial) {
            // Make sure our collection index will be loaded
            $partial[$association->getPropertyName()] = [];
        }

        $isInheritanceAware = $definition->isInheritanceAware() && $context->considerInheritance();

        if ($isInheritanceAware) {
            $parentIds = array_values(array_filter($collection->map(fn (Entity $entity) => $entity->get('parentId'))));

            $ids = array_unique([...$ids, ...$parentIds]);
        }

        $fieldCriteria->addFilter(new EqualsAnyFilter($propertyAccessor, $ids));

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
        /** @var EntityCollection<Entity> $collectionClass */
        $collectionClass = $referenceClass->getCollectionClass();

        $data = $this->_read(
            $fieldCriteria,
            $referenceClass,
            $context,
            new $collectionClass(),
            $referenceClass->getFields()->getBasicFields(),
            false,
            $partial
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

            if ($parentId === null) {
                continue;
            }

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

    /**
     * @param EntityCollection<Entity> $collection
     * @param array<string, mixed> $partial
     */
    private function loadManyToManyOverExtension(
        Criteria $criteria,
        ManyToManyAssociationField $association,
        Context $context,
        EntityCollection $collection,
        array $partial
    ): void {
        //collect all ids of many to many association which already stored inside the struct instances
        $ids = $this->collectManyToManyIds($collection, $association);

        $criteria->setIds($ids);

        $referenceClass = $association->getToManyReferenceDefinition();
        /** @var EntityCollection<Entity> $collectionClass */
        $collectionClass = $referenceClass->getCollectionClass();

        $data = $this->_read(
            $criteria,
            $referenceClass,
            $context,
            new $collectionClass(),
            $referenceClass->getFields()->getBasicFields(),
            false,
            $partial
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

    /**
     * @param EntityCollection<Entity> $collection
     * @param array<string, mixed> $partial
     */
    private function loadManyToManyWithCriteria(
        Criteria $fieldCriteria,
        ManyToManyAssociationField $association,
        Context $context,
        EntityCollection $collection,
        array $partial
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

        $mapping = $query->executeQuery()->fetchAllKeyValue();

        $ids = [];
        foreach ($mapping as &$row) {
            $row = array_filter(explode(',', (string) $row));
            foreach ($row as $id) {
                $ids[] = $id;
            }
        }
        unset($row);

        $fieldCriteria->setIds($ids);

        $referenceClass = $association->getToManyReferenceDefinition();
        /** @var EntityCollection<Entity> $collectionClass */
        $collectionClass = $referenceClass->getCollectionClass();
        $data = $this->_read(
            $fieldCriteria,
            $referenceClass,
            $context,
            new $collectionClass(),
            $referenceClass->getFields()->getBasicFields(),
            false,
            $partial
        );

        /** @var Entity $struct */
        foreach ($collection as $struct) {
            $structData = new $collectionClass();

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

    /**
     * @param EntityCollection<Entity> $collection
     *
     * @return array<string, string[]>
     */
    private function fetchPaginatedOneToManyMapping(
        EntityDefinition $definition,
        OneToManyAssociationField $association,
        Context $context,
        EntityCollection $collection,
        Criteria $fieldCriteria
    ): array {
        $sortings = $fieldCriteria->getSorting();

        // Remove first entry
        array_shift($sortings);

        //build query based on provided association criteria (sortings, search, filter)
        $query = $this->criteriaQueryBuilder->build(
            new QueryBuilder($this->connection),
            $association->getReferenceDefinition(),
            $fieldCriteria,
            $context
        );

        $foreignKey = $association->getReferenceField();

        if (!$association->getReferenceDefinition()->getField('id')) {
            throw new \RuntimeException(
                sprintf(
                    'Paginated to many association must have an id field. No id field found for association %s.%s',
                    $definition->getEntityName(),
                    $association->getPropertyName()
                )
            );
        }

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

        foreach ($query->getQueryPart('orderBy') as $i => $sorting) {
            // The first order is the primary key
            if ($i === 0) {
                continue;
            }
            --$i;

            // Strip the ASC/DESC at the end of the sort
            $query->addSelect(\sprintf('%s as sort_%d', substr((string) $sorting, 0, -4), $i));
        }

        $root = EntityDefinitionQueryHelper::escape($definition->getEntityName());

        //create a wrapper query which select the root primary key and the grouped reference ids
        $wrapper = $this->connection->createQueryBuilder();
        $wrapper->select(
            [
                'LOWER(HEX(' . $root . '.id)) as id',
                'LOWER(HEX(child.id)) as child_id',
            ]
        );

        foreach ($sortings as $i => $sorting) {
            $wrapper->addOrderBy(sprintf('sort_%s', $i), $sorting->getDirection());
        }

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
            fn (Entity $entity) => Uuid::fromHexToBytes($entity->getUniqueIdentifier())
        );

        if ($definition->isInheritanceAware() && $context->considerInheritance()) {
            /** @var Entity $entity */
            foreach ($collection->getElements() as $entity) {
                if ($entity->get('parentId')) {
                    $bytes[$entity->get('parentId')] = Uuid::fromHexToBytes($entity->get('parentId'));
                }
            }
        }

        $wrapper->setParameter('rootIds', $bytes, ArrayParameterType::STRING);

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

        $rows = $wrapper->executeQuery()->fetchAllAssociative();

        $grouped = [];
        foreach ($rows as $row) {
            $id = (string) $row['id'];

            if (!isset($grouped[$id])) {
                $grouped[$id] = [];
            }

            if (empty($row['child_id'])) {
                continue;
            }

            $grouped[$id][] = (string) $row['child_id'];
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
            $field = $definition->getFields()->get($fieldName);
            if (!$field) {
                $this->logger->warning(
                    sprintf('Criteria association "%s" could not be resolved. Double check your Criteria!', $fieldName)
                );

                continue;
            }

            $fields->add($field);
        }

        return $fields;
    }

    /**
     * @param EntityCollection<Entity> $collection
     * @param array<string, mixed> $partial
     */
    private function loadToOne(
        AssociationField $association,
        Context $context,
        EntityCollection $collection,
        Criteria $criteria,
        array $partial
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

        $related = array_filter($collection->map(function (Entity $entity) use ($association) {
            if ($association->is(Extension::class)) {
                return $entity->getExtension($association->getPropertyName());
            }

            return $entity->get($association->getPropertyName());
        }));

        $referenceDefinition = $association->getReferenceDefinition();
        $collectionClass = $referenceDefinition->getCollectionClass();

        if ($partial !== []) {
            $collectionClass = EntityCollection::class;
        }

        $fields = $referenceDefinition->getFields()->getBasicFields();
        $fields = $this->addAssociationFieldsToCriteria($associationCriteria, $referenceDefinition, $fields);

        // This line removes duplicate entries, so after fetchAssociations the association must be reassigned
        $relatedCollection = new $collectionClass();
        if (!$relatedCollection instanceof EntityCollection) {
            throw new \RuntimeException(sprintf('Collection class %s has to be an instance of EntityCollection', $collectionClass));
        }

        $relatedCollection->fill($related);

        $this->fetchAssociations($associationCriteria, $referenceDefinition, $context, $relatedCollection, $fields, $partial);

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

    /**
     * @param EntityCollection<Entity> $collection
     * @param array<string, mixed> $partial
     *
     * @return EntityCollection<Entity>
     */
    private function fetchAssociations(
        Criteria $criteria,
        EntityDefinition $definition,
        Context $context,
        EntityCollection $collection,
        FieldCollection $fields,
        array $partial
    ): EntityCollection {
        if ($collection->count() <= 0) {
            return $collection;
        }

        foreach ($fields as $association) {
            if (!$association instanceof AssociationField) {
                continue;
            }

            if ($association instanceof OneToOneAssociationField || $association instanceof ManyToOneAssociationField) {
                $this->loadToOne($association, $context, $collection, $criteria, $partial[$association->getPropertyName()] ?? []);

                continue;
            }

            if ($association instanceof OneToManyAssociationField) {
                $this->loadOneToMany($criteria, $definition, $association, $context, $collection, $partial[$association->getPropertyName()] ?? []);

                continue;
            }

            if ($association instanceof ManyToManyAssociationField) {
                $this->loadManyToMany($criteria, $association, $context, $collection, $partial[$association->getPropertyName()] ?? []);
            }
        }

        foreach ($collection as $struct) {
            $struct->removeExtension(self::INTERNAL_MAPPING_STORAGE);
        }

        return $collection;
    }
}
