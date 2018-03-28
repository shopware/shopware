<?php declare(strict_types=1);

namespace Shopware\Api\Entity\Dbal;

use Doctrine\DBAL\Connection;
use Shopware\Api\Entity\Entity;
use Shopware\Api\Entity\EntityCollection;
use Shopware\Api\Entity\EntityDefinition;
use Shopware\Api\Entity\Field\AssociationInterface;
use Shopware\Api\Entity\Field\Field;
use Shopware\Api\Entity\Field\ManyToManyAssociationField;
use Shopware\Api\Entity\Field\ManyToOneAssociationField;
use Shopware\Api\Entity\Field\OneToManyAssociationField;
use Shopware\Api\Entity\Field\TranslatedField;
use Shopware\Api\Entity\FieldCollection;
use Shopware\Api\Entity\Read\EntityReaderInterface;
use Shopware\Api\Entity\Search\Criteria;
use Shopware\Api\Entity\Search\EntitySearcherInterface;
use Shopware\Api\Entity\Search\Query\TermsQuery;
use Shopware\Api\Entity\Write\FieldAware\StorageAware;
use Shopware\Api\Entity\Write\Flag\CascadeDelete;
use Shopware\Api\Entity\Write\Flag\Deferred;
use Shopware\Api\Entity\Write\Flag\Extension;
use Shopware\Api\Entity\Write\Flag\Inherited;
use Shopware\Context\Struct\ShopContext;
use Shopware\Framework\Struct\ArrayStruct;
use Shopware\Framework\Struct\Struct;

/**
 * Reads entities in specify data form (basic, detail, dynamic).
 *
 * Basic => contains all scalar fields of the definition and associations which marked as "loadInBasic=true"
 * Detail => contains all fields, excluded fields which marked with writeOnly
 * Dynamic => allows to specify which fields should be loaded
 */
class EntityReader implements EntityReaderInterface
{
    public const MANY_TO_MANY_EXTENSION_STORAGE = 'many_to_many_storage';

    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var EntitySearcherInterface
     */
    private $searcher;

    /**
     * @var EntityHydrator
     */
    private $hydrator;

    public function __construct(
        Connection $connection,
        EntitySearcherInterface $searcher,
        EntityHydrator $hydrator
    ) {
        $this->connection = $connection;
        $this->searcher = $searcher;
        $this->hydrator = $hydrator;
    }

    public function readDetail(string $definition, array $ids, ShopContext $context): EntityCollection
    {
        /** @var EntityDefinition $definition */
        $collectionClass = $definition::getDetailCollectionClass();

        $structClass = $definition::getDetailStructClass();

        return $this->read(
            $ids,
            $definition,
            $context,
            new $structClass(),
            new $collectionClass(),
            $definition::getFields()->getDetailProperties()
        );
    }

    public function readBasic(string $definition, array $ids, ShopContext $context): EntityCollection
    {
        /** @var EntityDefinition $definition */
        $collectionClass = $definition::getBasicCollectionClass();

        $structClass = $definition::getBasicStructClass();

        return $this->read(
            $ids,
            $definition,
            $context,
            new $structClass(),
            new $collectionClass(),
            $definition::getFields()->getBasicProperties()
        );
    }

    public function readRaw(string $definition, array $ids, ShopContext $context): EntityCollection
    {
        /** @var EntityDefinition $definition */
        $collectionClass = EntityCollection::class;

        $structClass = ArrayStruct::class;

        $details = $this->read(
            $ids,
            $definition,
            $context,
            new $structClass(),
            new $collectionClass(),
            $definition::getFields()->getDetailProperties(),
            true
        );

        $this->removeInheritance($definition, $details);

        return $details;
    }

    private function read(array $ids, string $definition, ShopContext $context, Entity $entity, EntityCollection $collection, FieldCollection $fields, bool $raw = false): EntityCollection
    {
        if (empty($ids)) {
            return $collection;
        }

        /** @var EntityDefinition|string $definition */
        $rows = $this->fetch($ids, $definition, $context, $fields, $raw);

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
            $this->loadOneToMany($definition, $association, $context, $collection);
        }

        /** @var ManyToManyAssociationField[] $associations */
        $associations = $fields->filterInstance(ManyToManyAssociationField::class);
        foreach ($associations as $association) {
            $this->loadManyToMany($association, $context, $collection);
        }

        /** @var Entity $struct */
        foreach ($collection as $struct) {
            $struct->removeExtension(self::MANY_TO_MANY_EXTENSION_STORAGE);
        }

        $collection->sortByIdArray($ids);

        return $collection;
    }

    private function joinBasic(
        string $definition,
        ShopContext $context,
        string $root,
        QueryBuilder $query,
        FieldCollection $fields,
        bool $raw = false
    ): void {
        /** @var EntityDefinition $definition */
        $filtered = $fields->fmap(function (Field $field) {
            if ($field->is(Deferred::class)) {
                return null;
            }

            return $field;
        });

        $parent = null;

        if ($definition::getParentPropertyName() && !$raw) {
            /** @var EntityDefinition|string $definition */
            $parent = $definition::getFields()->get($definition::getParentPropertyName());
            EntityDefinitionQueryHelper::joinManyToOne($definition, $root, $parent, $query, $context);
        }

        foreach ($filtered as $field) {
            //translated fields are handled after loop all together
            if ($field instanceof TranslatedField) {
                continue;
            }

            //self references can not be resolved, otherwise we get an endless loop
            if ($field instanceof AssociationInterface && $field->getReferenceClass() === $definition) {
                continue;
            }

            //many to one associations can be directly fetched in same query
            if ($field instanceof ManyToOneAssociationField) {
                /** @var EntityDefinition|string $reference */
                $reference = $field->getReferenceClass();

                $basics = $reference::getFields()->getBasicProperties();

                if ($this->requiresToManyAssociation($reference, $basics)) {
                    continue;
                }

                EntityDefinitionQueryHelper::joinManyToOne($definition, $root, $field, $query, $context);

                $alias = $root . '.' . $field->getPropertyName();
                $this->joinBasic($field->getReferenceClass(), $context, $alias, $query, $basics, $raw);

                continue;
            }

            //add sub select for many to many field
            if ($field instanceof ManyToManyAssociationField) {
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
                continue;
            }
        }

        $translatedFields = $fields->fmap(function (Field $field) {
            if ($field instanceof TranslatedField) {
                return $field;
            }

            return null;
        });

        if (count($translatedFields) <= 0) {
            return;
        }

        EntityDefinitionQueryHelper::addTranslationSelect($root, $definition, $query, $context, $translatedFields, $raw);
    }

    /**
     * @param array                   $ids
     * @param string|EntityDefinition $definition
     * @param ShopContext             $context
     * @param FieldCollection         $fields
     *
     * @return array
     */
    private function fetch(array $ids, string $definition, ShopContext $context, FieldCollection $fields, bool $raw): array
    {
        $table = $definition::getEntityName();

        $query = EntityDefinitionQueryHelper::getBaseQuery($this->connection, $definition, $context);

        $this->joinBasic($definition, $context, $table, $query, $fields, $raw);

        $query->andWhere(EntityDefinitionQueryHelper::escape($table) . '.`id` IN (:ids)');
        $query->setParameter('ids', array_values(EntityDefinitionQueryHelper::uuidStringsToBytes($ids)), Connection::PARAM_STR_ARRAY);

        return $query->execute()->fetchAll();
    }

    /**
     * @param string|EntityDefinition   $definition
     * @param ManyToOneAssociationField $association
     * @param ShopContext               $context
     * @param EntityCollection          $collection
     */
    private function loadManyToOne(string $definition, ManyToOneAssociationField $association, ShopContext $context, EntityCollection $collection)
    {
        $reference = $association->getReferenceClass();

        $fields = $reference::getFields()->getBasicProperties();
        if (!$this->requiresToManyAssociation($reference, $fields)) {
            return;
        }

        $field = $definition::getFields()->getByStorageName($association->getStorageName());

        $ids = $collection->map(function (Entity $entity) use ($field) {
            return $entity->get($field->getPropertyName());
        });

        $data = $this->readBasic($association->getReferenceClass(), $ids, $context);

        /** @var Entity $struct */
        foreach ($collection as $struct) {
            $id = $struct->get($field->getPropertyName());

            if ($association->is(Extension::class)) {
                $struct->addExtension($association->getPropertyName(), $data->get($id));
                continue;
            }

            $struct->assign([
                $association->getPropertyName() => $data->get($id),
            ]);
        }
    }

    private function loadOneToMany(string $definition, OneToManyAssociationField $association, ShopContext $context, EntityCollection $collection): void
    {
        $ids = array_values($collection->getIds());
        /** @var string|EntityDefinition $definition */
        $parentId = null;
        if ($definition::getParentPropertyName()) {
            /** @var ManyToOneAssociationField $parent */
            $parent = $definition::getFields()->get($definition::getParentPropertyName());
            $parentId = $definition::getFields()->getByStorageName($parent->getStorageName());
            $parentIds = $collection->map(function (Entity $entity) use ($parentId) {
                return $entity->get($parentId->getPropertyName());
            });
            $parentIds = array_values(array_filter($parentIds));
            $ids = array_unique(array_merge($ids, $parentIds));
        }

        $reference = $association->getReferenceClass();

        $field = $reference::getFields()->getByStorageName($association->getReferenceField());

        $criteria = new Criteria();
        $criteria->addFilter(new TermsQuery($reference::getEntityName() . '.' . $field->getPropertyName(), $ids));

        $associationIds = $this->searcher->search($reference, $criteria, $context);

        $data = $this->readBasic($reference, $associationIds->getIds(), $context);

        $flat = json_decode(json_encode($data->getElements()), true);

        $mapping = array_column($flat, $field->getPropertyName(), 'id');

        $hasInheritance = $definition::getParentPropertyName() && $association->is(Inherited::class);

        /** @var Struct|Entity $struct */
        foreach ($collection as $struct) {
            $mappingIds = array_intersect($mapping, [$struct->getId()]);

            if (count($mappingIds) <= 0 && $hasInheritance) {
                $mappingIds = array_intersect($mapping, [$struct->get($parentId->getPropertyName())]);
            }

            $structData = $data->getList(array_keys($mappingIds));

            if ($association->is(Extension::class)) {
                $struct->addExtension($association->getPropertyName(), $structData);
                continue;
            }

            $struct->assign([
                $association->getPropertyName() => $structData,
            ]);
        }
    }

    private function loadManyToMany(ManyToManyAssociationField $association, ShopContext $context, EntityCollection $collection): void
    {
        //collect all ids of many to many association which already stored inside the struct instances
        $ids = $this->collectManyToManyIds($collection, $association);

        $data = $this->readBasic($association->getReferenceDefinition(), $ids, $context);

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

        $query->addSelect(
            str_replace(
                [
                    '#alias#',
                    '#mapping_reference_column#',
                    '#mapping_table#',
                    '#mapping_local_column#',
                    '#root#',
                    '#source_column#',
                    '#property#',
                ],
                [
                    EntityDefinitionQueryHelper::escape($root . '.' . $field->getPropertyName() . '.mapping'),
                    EntityDefinitionQueryHelper::escape($field->getMappingReferenceColumn()),
                    EntityDefinitionQueryHelper::escape($mapping::getEntityName()),
                    EntityDefinitionQueryHelper::escape($field->getMappingLocalColumn()),
                    EntityDefinitionQueryHelper::escape($root),
                    EntityDefinitionQueryHelper::escape($field->getLocalField()),
                    EntityDefinitionQueryHelper::escape($root . '.' . $field->getPropertyName()),
                ],
                '(SELECT GROUP_CONCAT(HEX(#alias#.#mapping_reference_column#) SEPARATOR \'||\')
                  FROM #mapping_table# #alias#
                  WHERE #alias#.#mapping_local_column# = #root#.#source_column#
                  ' . $versionCondition . '
                  ' . $catalogCondition . '
                  ) as #property#'
            )
        );
    }

    private function collectManyToManyIds(EntityCollection $collection, AssociationInterface $association): array
    {
        $ids = [];
        foreach ($collection as $struct) {
            /** @var Field $association */
            $tmp = $struct->getExtension(self::MANY_TO_MANY_EXTENSION_STORAGE)->get($association->getPropertyName());
            foreach ($tmp as $id) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    private function requiresToManyAssociation(string $definition, FieldCollection $fields): bool
    {
        foreach ($fields as $field) {
            if (!$field instanceof AssociationInterface) {
                continue;
            }

            if ($field->is(Deferred::class)) {
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

            if ($this->requiresToManyAssociation($reference, $reference::getFields()->getBasicProperties())) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param string $definition
     * @param $details
     */
    private function removeInheritance(string $definition, $details): void
    {
        $inherited = $definition::getFields()->filterInstance(AssociationInterface::class)->filterByFlag(
            Inherited::class
        );

        foreach ($details as $detail) {
            foreach ($inherited as $association) {
                if ($association instanceof ManyToOneAssociationField) {
                    $joinField = $association->getJoinField();
                    $idField = $association->getStorageName();
                } else {
                    if ($association instanceof ManyToManyAssociationField) {
                        $joinField = $association->getLocalField();
                        $idField = 'id';
                    } else {
                        if ($association instanceof OneToManyAssociationField) {
                            $joinField = $association->getLocalField();
                            $idField = 'id';
                        }
                    }
                }

                $join = $definition::getFields()->getByStorageName($joinField);
                $id = $definition::getFields()->getByStorageName($idField);

                /** @var ArrayStruct $detail */
                $idValue = $detail->get($id->getPropertyName());
                $joinValue = $detail->get($join->getPropertyName());

                if ($idValue !== $joinValue) {
                    $detail->offsetUnset($association->getPropertyName());
                }
            }
        }
    }
}
